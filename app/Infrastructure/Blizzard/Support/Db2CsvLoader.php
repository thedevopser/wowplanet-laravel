<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Support;

class Db2CsvLoader
{
    /**
     * Load a CSV file and build a map using header names for column lookup.
     *
     * @return array<int, int>
     */
    public static function loadMapByHeaders(string $filename, string $keyHeader, string $valueHeader): array
    {
        $path = storage_path('app/blizzard/'.$filename);
        if (! file_exists($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $keyIdx = (int) array_search($keyHeader, $headers, true);
        $valueIdx = (int) array_search($valueHeader, $headers, true);

        $map = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $map[(int) $row[$keyIdx]] = (int) $row[$valueIdx];
        }

        fclose($handle);

        return $map;
    }
}
