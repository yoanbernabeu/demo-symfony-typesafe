<?php

namespace App\Tests\Dataset;

trait DatasetFixtureTrait
{
    /**
     * Writes a file shaped like the real one: byte order mark, ";" separator, quoted header and extra columns.
     *
     * @param list<list<string>> $rows Each row is: id, day, user name, text, feeling
     */
    private function writeDataset(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF".'"ID expérience";"Écrit le";"Pseudonyme usager";Description;"Ressenti usager"'."\n");
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }
        fclose($handle);
    }
}
