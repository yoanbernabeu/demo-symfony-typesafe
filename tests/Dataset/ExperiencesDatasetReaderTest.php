<?php

namespace App\Tests\Dataset;

use App\Dataset\DatasetRow;
use App\Dataset\ExperiencesDatasetReader;
use PHPUnit\Framework\TestCase;

final class ExperiencesDatasetReaderTest extends TestCase
{
    use DatasetFixtureTrait;

    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/demojev_'.bin2hex(random_bytes(4)).'.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testItOnlyKeepsTheIdTheDateAndTheText(): void
    {
        $this->writeDataset($this->path, [
            ['280027', '2025-03-14', 'Jean D.', 'Mon dossier est bloqué depuis trois mois.', 'Négatif'],
        ]);

        $experiences = iterator_to_array((new ExperiencesDatasetReader($this->path))->read(minLength: 10));

        self::assertCount(1, $experiences);
        self::assertSame('280027', $experiences[0]->id);
        self::assertSame('2025-03-14 00:00:00', $experiences[0]->writtenAt->format('Y-m-d H:i:s'));
        self::assertSame('Mon dossier est bloqué depuis trois mois.', $experiences[0]->text);
        self::assertSame(['id', 'writtenAt', 'text'], array_keys(get_object_vars($experiences[0])));
    }

    public function testItReadsTextsSpreadOverSeveralLinesAndContainingTheSeparator(): void
    {
        $this->writeDataset($this->path, [
            ['1', '2025-01-02', 'A', "Bonjour ;\n\nle site affiche \"Erreur 500\"   depuis hier.\r\nMerci.", 'Négatif'],
        ]);

        $experiences = iterator_to_array((new ExperiencesDatasetReader($this->path))->read(minLength: 10));

        self::assertSame('Bonjour ; le site affiche "Erreur 500" depuis hier. Merci.', $experiences[0]->text);
    }

    public function testItFiltersOnThePeriodAndOnTheLengthOfTheText(): void
    {
        $this->writeDataset($this->path, [
            ['old', '2024-12-31', 'A', str_repeat('a', 100), 'Positif'],
            ['first-day', '2025-01-01', 'B', str_repeat('b', 100), 'Positif'],
            ['too-short', '2025-06-01', 'C', 'Merci', 'Positif'],
            ['too-long', '2025-06-01', 'D', str_repeat('d', 300), 'Positif'],
            ['last-day', '2025-12-31', 'E', str_repeat('é', 200), 'Positif'],
            ['new', '2026-01-01', 'F', str_repeat('f', 100), 'Positif'],
        ]);

        $experiences = (new ExperiencesDatasetReader($this->path))->read(new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31 18:00'), 80, 200);

        self::assertSame(['first-day', 'last-day'], array_map(static fn (DatasetRow $row): string => $row->id, iterator_to_array($experiences)));
    }

    public function testItSkipsBrokenRows(): void
    {
        $this->writeDataset($this->path, [
            ['1', 'not-a-date', 'A', str_repeat('a', 100), 'Positif'],
            ['2'],
            ['3', '2025-05-05', 'C', str_repeat('c', 100), 'Positif'],
        ]);

        $experiences = iterator_to_array((new ExperiencesDatasetReader($this->path))->read());

        self::assertSame(['3'], array_column($experiences, 'id'));
    }

    public function testItExplainsHowToGetTheDatasetWhenItIsMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('app:dataset:download');

        iterator_to_array((new ExperiencesDatasetReader($this->path))->read());
    }

    public function testItFailsWhenAColumnDisappeared(): void
    {
        file_put_contents($this->path, "\xEF\xBB\xBF\"ID expérience\";\"Écrit le\";Texte\n1;2025-01-01;Bonjour\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Description"');

        iterator_to_array((new ExperiencesDatasetReader($this->path))->read());
    }
}
