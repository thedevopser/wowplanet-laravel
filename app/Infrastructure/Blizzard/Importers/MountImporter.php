<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Models\WowMount;
use Illuminate\Support\Facades\File;

class MountImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    public function import(): void
    {
        $this->info('Loading mounts from DB2 CSV data...');

        $mounts = $this->parseMountCsv();
        if ($mounts === []) {
            $this->info('ERROR: mount.csv not found or empty.');

            return;
        }

        $this->info(sprintf('Found %d mounts in CSV.', count($mounts)));

        $count = 0;
        foreach ($mounts as $mount) {
            WowMount::query()->updateOrCreate(['id' => $mount['id']], [
                'name_fr' => $mount['name_fr'],
                'source_spell_id' => $mount['source_spell_id'] > 0 ? $mount['source_spell_id'] : null,
                'is_active' => true,
            ]);
            $count++;
            if ($count % 500 === 0) {
                $this->info(sprintf('  Saved %d...', $count));
            }
        }

        $this->info(sprintf('Mount import complete: %d mounts.', $count));
    }

    /**
     * @return list<array{id: int, name_fr: string, source_spell_id: int}>
     */
    private function parseMountCsv(): array
    {
        $csvPath = storage_path('app/blizzard/mount.csv');
        if (! File::exists($csvPath)) {
            return [];
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            return [];
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        if ($headers === false) {
            fclose($handle);

            return [];
        }

        $nameIdx = (int) array_search('Name_lang', $headers, true);
        $idIdx = (int) array_search('ID', $headers, true);
        $spellIdx = (int) array_search('SourceSpellID', $headers, true);

        $mounts = [];
        $skipped = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $name = trim($row[$nameIdx] ?? '');
            if ($name === '') {
                $skipped++;

                continue;
            }

            $mounts[] = [
                'id' => (int) $row[$idIdx],
                'name_fr' => $name,
                'source_spell_id' => (int) ($row[$spellIdx] ?? 0),
            ];
        }

        fclose($handle);

        if ($skipped > 0) {
            $this->info(sprintf('  Skipped %d mounts with empty names.', $skipped));
        }

        return $mounts;
    }

    public function importIcons(): void
    {
        $this->info('Fetching mount icons...');

        /** @var \Illuminate\Database\Eloquent\Collection<int, WowMount> $mounts */
        $mounts = WowMount::query()->whereNull('icon_url')->get();
        $this->info(sprintf('  %d mounts need icons.', $mounts->count()));
        $count = 0;
        $skipped = 0;

        foreach ($mounts as $mount) {
            $this->delayIconRequest();

            $detail = $this->fetchWithRetry('data/wow/mount/'.$mount->id);
            if (! $detail) {
                $skipped++;

                continue;
            }

            /** @var list<array{id: int}> $displays */
            $displays = $detail['creature_displays'] ?? [];
            $displayId = $displays[0]['id'] ?? null;
            if ($displayId === null) {
                $skipped++;

                continue;
            }

            $this->delayIconRequest();
            $media = $this->fetchWithRetry('data/wow/media/creature-display/'.$displayId);
            if (! $media) {
                $skipped++;

                continue;
            }

            /** @var list<array{key: string, value: string}> $assets */
            $assets = $media['assets'] ?? [];
            $iconUrl = $assets[0]['value'] ?? null;
            if ($iconUrl) {
                $mount->update(['icon_url' => $iconUrl]);
                $count++;
            }

            if ($count % 100 === 0 && $count > 0) {
                $this->info(sprintf('  Icons fetched: %d / skipped: %d...', $count, $skipped));
            }
        }

        $this->info(sprintf('Mount icon import complete: %d icons, %d skipped.', $count, $skipped));
    }
}
