<?php

namespace App\Tests\Triage;

use App\Entity\Experience;
use App\Triage\ExperienceTriage;
use App\Triage\Intention;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExperienceTriageTest extends TestCase
{
    public function testItAsksThreeQuestionsAboutTheTextAndTypesTheAnswers(): void
    {
        $jev = new JevResponses(static fn (): MockResponse => JevResponses::answer('debloquer', 0.82, 2.4, 0.97, 410, 66));
        $triage = new ExperienceTriage($jev->createPlatform(), new NullLogger());

        $results = $triage->triage([new Experience(42, new \DateTimeImmutable('2026-03-01'), 'Le site affiche une erreur depuis trois semaines.')]);

        self::assertSame(['Le site affiche une erreur depuis trois semaines.'], array_column($jev->requests, 'state'));
        self::assertSame(['intention' => 'choice', 'priority' => 'score', 'bug' => 'noul'], array_map(static fn (array $q): string => $q['type'], $jev->requests[0]['questions']));
        self::assertSame(array_map(static fn (Intention $i): string => $i->value, Intention::cases()), array_keys($jev->requests[0]['questions']['intention']['criteria']));

        self::assertSame([42], array_keys($results));
        self::assertSame(Intention::Unblock, $results[42]->intention);
        self::assertSame(0.82, $results[42]->intentionConfidence);
        self::assertSame(2.4, $results[42]->priority);
        self::assertSame(0.97, $results[42]->bugProbability);
        self::assertSame(476, $results[42]->tokens);
    }

    public function testAnExperienceJevFailsOnIsLeftOutWithoutLosingTheOthers(): void
    {
        $jev = new JevResponses(static fn (string $state): MockResponse => str_contains($state, 'panne')
            ? new MockResponse('{"detail": "Service unavailable"}', ['http_code' => 503])
            : JevResponses::answer('remercier', 0.99, 0.0, 0.01));
        $triage = new ExperienceTriage($jev->createPlatform(), new NullLogger());

        $results = $triage->triage([
            new Experience(1, new \DateTimeImmutable('2026-03-01'), 'Merci, tout a très bien fonctionné.'),
            new Experience(2, new \DateTimeImmutable('2026-03-01'), 'Celui-ci tombe pendant une panne de Jev.'),
            new Experience(3, new \DateTimeImmutable('2026-03-01'), 'Merci encore pour votre accueil.'),
        ]);

        self::assertSame([1, 3], array_keys($results));
        self::assertSame(Intention::Thanks, $results[3]->intention);
    }
}
