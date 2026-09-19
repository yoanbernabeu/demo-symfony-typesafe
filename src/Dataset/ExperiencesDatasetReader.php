<?php

namespace App\Dataset;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Streams the rows out of the downloaded dataset, one at a time, so the
 * whole file never has to fit in memory.
 */
final class ExperiencesDatasetReader
{
    private const ID_COLUMN = 'ID expérience';
    private const DATE_COLUMN = 'Écrit le';
    private const TEXT_COLUMN = 'Description';

    public function __construct(
        #[Autowire(param: 'app.experiences_dataset_path')] private readonly string $path,
    ) {
    }

    /**
     * @param \DateTimeInterface|null $since     Only keep what was written on or after this day
     * @param \DateTimeInterface|null $until     Only keep what was written on or before this day
     * @param int                     $minLength Shortest text to keep, in characters
     * @param int                     $maxLength Longest text to keep, in characters
     *
     * @return \Generator<int, DatasetRow>
     */
    public function read(?\DateTimeInterface $since = null, ?\DateTimeInterface $until = null, int $minLength = 80, int $maxLength = 1500): \Generator
    {
        if (!is_file($this->path) || false === $handle = fopen($this->path, 'r')) {
            throw new \RuntimeException(\sprintf('The dataset was not found at "%s". Run "bin/console app:dataset:download" first.', $this->path));
        }

        try {
            $columns = $this->readHeader($handle);
            $since = $since?->format('Y-m-d');
            $until = $until?->format('Y-m-d');

            // The file uses ";" as separator and spreads its quoted descriptions over several lines, which fgetcsv() handles
            while (false !== $row = fgetcsv($handle, null, ';', '"', '')) {
                if (!isset($row[$columns['id']], $row[$columns['date']], $row[$columns['text']])) {
                    continue;
                }

                $day = substr($row[$columns['date']], 0, 10);
                if ((null !== $since && $day < $since) || (null !== $until && $day > $until)) {
                    continue;
                }

                $text = trim(preg_replace('/\s+/u', ' ', $row[$columns['text']]) ?? '');
                $length = mb_strlen($text);
                if ($length < $minLength || $length > $maxLength) {
                    continue;
                }

                if (false === $writtenAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $day)) {
                    continue;
                }

                yield new DatasetRow($row[$columns['id']], $writtenAt, $text);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     *
     * @return array{id: int, date: int, text: int}
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle, null, ';', '"', '');
        if (!\is_array($header)) {
            throw new \RuntimeException(\sprintf('The dataset at "%s" is empty.', $this->path));
        }

        // The file starts with a UTF-8 byte order mark, which would otherwise stick to the first column name
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(static fn (?string $name): string => trim((string) $name, " \"\t"), $header);

        $columns = [];
        foreach (['id' => self::ID_COLUMN, 'date' => self::DATE_COLUMN, 'text' => self::TEXT_COLUMN] as $key => $name) {
            if (false === $index = array_search($name, $header, true)) {
                throw new \RuntimeException(\sprintf('The dataset has no "%s" column anymore, its format must have changed.', $name));
            }

            $columns[$key] = $index;
        }

        return $columns;
    }
}
