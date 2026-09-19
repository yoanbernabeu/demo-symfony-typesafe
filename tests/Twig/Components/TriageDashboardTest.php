<?php

namespace App\Tests\Twig\Components;

use App\Entity\Experience;
use App\Message\TriageExperiences;
use App\Repository\ExperienceRepository;
use App\Tests\DatabaseTrait;
use App\Twig\Components\TriageDashboard;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class TriageDashboardTest extends KernelTestCase
{
    use DatabaseTrait;
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        $entityManager = $this->resetDatabase();
        for ($id = 1; $id <= 3; ++$id) {
            $entityManager->persist(new Experience($id, new \DateTimeImmutable('2026-04-01'), "Demande numéro $id."));
        }
        $entityManager->flush();
        $entityManager->clear();
    }

    public function testItStartsIdleAndLaunchesTheTriage(): void
    {
        $component = $this->createLiveComponent(TriageDashboard::class);

        $idle = (string) $component->render();
        self::assertStringContainsString('Lancer la qualification', $idle);
        self::assertStringContainsString('delay(5000)|$render', $idle);

        $component->call('launch');

        $running = (string) $component->render();
        self::assertStringContainsString('Jev travaille', $running);
        self::assertStringContainsString('delay(1000)|$render', $running, 'It polls every second for as long as experiences are pending.');
        self::assertStringContainsString('qualifiées sur 3', $running);

        self::assertSame(3, self::getContainer()->get(ExperienceRepository::class)->countTriageRequested());
        $transport = self::getContainer()->get('messenger.transport.async');
        \assert($transport instanceof InMemoryTransport);
        self::assertCount(1, $transport->getSent());
        self::assertInstanceOf(TriageExperiences::class, $transport->getSent()[0]->getMessage());
    }
}
