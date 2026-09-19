<?php

namespace App\Tests\Controller;

use App\Entity\Experience;
use App\Triage\Intention;
use App\Triage\TriageResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InboxControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = [$entityManager->getClassMetadata(Experience::class)];
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        // 25 experiences, one per day: number 25 is the most recent
        for ($i = 1; $i <= 25; ++$i) {
            $entityManager->persist(new Experience($i, new \DateTimeImmutable(\sprintf('2025-03-%02d', $i)), "Demande de test numéro $i à propos de ma carte grise."));
        }
        $entityManager->persist(new Experience(100, new \DateTimeImmutable('2025-01-01'), 'Le simulateur affiche 100% puis plus rien.'));
        $entityManager->persist(new Experience(101, new \DateTimeImmutable('2025-01-02'), 'Le simulateur affiche 1000 euros de trop.'));
        $entityManager->flush();
        $entityManager->clear();
    }

    public function testItListsTheMostRecentExperiencesFirst(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#experiences', '27');
        $rows = $crawler->filter('#experiences [data-slot="item"]');
        self::assertCount(20, $rows);
        self::assertStringContainsString('Demande n° 25', $rows->first()->text());
        self::assertSelectorTextContains('#experience', 'Aucune demande ouverte');
    }

    public function testItPaginates(): void
    {
        $crawler = $this->client->request('GET', '/?page=2');

        self::assertResponseIsSuccessful();
        self::assertCount(7, $crawler->filter('#experiences [data-slot="item"]'));
        self::assertSelectorTextContains('#experiences footer', 'Page 2 sur 2');

        $this->client->request('GET', '/?page=0');
        self::assertResponseStatusCodeSame(404);
    }

    public function testItSearchesTheTextLiterally(): void
    {
        $crawler = $this->client->request('GET', '/?q=100%25');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#experiences [data-slot="item"]');
        self::assertCount(1, $rows, 'The "%" typed by the user must not act as a wildcard.');
        self::assertStringContainsString('Demande n° 100', $rows->text());

        $this->client->request('GET', '/?q=licorne');
        self::assertSelectorTextContains('#experiences', 'Rien ne correspond à « licorne »');
    }

    public function testItShowsAnExperienceOnAFullPage(): void
    {
        $crawler = $this->client->request('GET', '/demandes/12');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#experience', 'Demande de test numéro 12 à propos de ma carte grise.');
        self::assertSelectorTextContains('#experience', '12/03/2025');
        self::assertCount(1, $crawler->filter('#experiences [data-slot="item"][aria-current="true"]'));
    }

    public function testATurboFrameRequestOnlyGetsTheFrame(): void
    {
        $this->client->request('GET', '/demandes/12', server: ['HTTP_TURBO_FRAME' => 'experience']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('turbo-frame#experience', 'Demande de test numéro 12');
        self::assertSelectorNotExists('#experiences');
        self::assertStringNotContainsString('<html', (string) $this->client->getResponse()->getContent());
    }

    public function testItShowsAndFiltersOnWhatJevAnswered(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $experience = $entityManager->find(Experience::class, 12);
        $experience->requestTriage(new \DateTimeImmutable('2026-09-19 10:00:00'));
        $experience->triage(new TriageResult(Intention::Unblock, 0.91, 2.4, 0.97, 480, 410), new \DateTimeImmutable('2026-09-19 10:00:01'));
        $entityManager->flush();

        $crawler = $this->client->request('GET', '/?intention=debloquer&bugs=1');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('#experiences [data-slot="item"]');
        self::assertCount(1, $rows);
        self::assertStringContainsString('Débloquer un dossier', $rows->text());
        self::assertStringContainsString('97 %', $rows->text());
        self::assertSelectorExists('#experiences a[aria-pressed="true"][href="/?bugs=1"]', 'Clicking the pressed intention releases it and keeps the other filter.');

        $this->client->request('GET', '/demandes/12');
        self::assertSelectorTextContains('#experience', 'à transmettre aux devs');
        self::assertSelectorTextContains('#experience', '410 ms');

        $this->client->request('GET', '/?intention=inconnue');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAnUnknownExperienceIsNotFound(): void
    {
        $this->client->request('GET', '/demandes/999');

        self::assertResponseStatusCodeSame(404);
    }
}
