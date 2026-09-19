<?php

namespace App\MessageHandler;

use App\Message\TriageExperiences;
use App\Repository\ExperienceRepository;
use App\Triage\ExperienceTriage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

#[AsMessageHandler]
final class TriageExperiencesHandler
{
    public function __construct(
        private readonly ExperienceRepository $experiences,
        private readonly ExperienceTriage $triage,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        #[Target('jev')] private readonly RateLimiterFactoryInterface $jevLimiter,
    ) {
    }

    public function __invoke(TriageExperiences $message): void
    {
        // Skips what a previous attempt already triaged, and what a reset withdrew from the triage
        $awaiting = array_filter(
            $this->experiences->findBy(['id' => $message->experienceIds]),
            static fn ($experience): bool => $experience->awaitsTriage(),
        );

        if ([] === $awaiting) {
            return;
        }

        // One token per request to Jev. When the minute is used up, the worker sleeps here until the next one
        $this->jevLimiter->create('triage')->reserve(\count($awaiting))->wait();

        $startedAt = microtime(true);
        $results = $this->triage->triage($awaiting);

        $now = $this->clock->now();
        foreach ($awaiting as $experience) {
            if (isset($results[$experience->getId()])) {
                $experience->triage($results[$experience->getId()], $now);
            }
        }
        $this->entityManager->flush();

        $this->logger->info('Jev triaged {triaged} of {asked} experiences in {duration} ms.', [
            'triaged' => \count($results),
            'asked' => \count($awaiting),
            'duration' => (int) round(1000 * (microtime(true) - $startedAt)),
        ]);

        if (\count($results) < \count($awaiting)) {
            // What succeeded is saved: retrying the message only asks Jev again about the rest
            throw new \RuntimeException(\sprintf('Jev failed on %d of %d experiences.', \count($awaiting) - \count($results), \count($awaiting)));
        }
    }
}
