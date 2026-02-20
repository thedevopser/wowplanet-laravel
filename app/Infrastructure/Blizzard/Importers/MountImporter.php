<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use App\Models\WowMount;

class MountImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    public function import(): void
    {
        $this->info('Fetching Mount Index...');
        $response = $this->fetchWithRetry('data/wow/mount/index');
        if (! $response) {
            $this->info('ERROR: Could not fetch mount index.');

            return;
        }

        /** @var list<array{id: int, name: string|null}> $mounts */
        $mounts = $response['mounts'] ?? [];
        $this->info('Found '.count($mounts).' mounts.');

        // mount.csv: Name_lang(0), SourceText_lang(1), Description_lang(2), ID(3), ..., SourceSpellID(7)
        $spellMap = Db2CsvLoader::loadMap('mount.csv', 3, 7);
        $this->info('  DB2 mount spell map: '.count($spellMap).' entries.');

        $skipped = 0;
        foreach ($mounts as $mount) {
            $mountName = $mount['name'] ?? '';
            if ($mountName === '') {
                $skipped++;

                continue;
            }

            WowMount::query()->updateOrCreate(['id' => $mount['id']], [
                'name_fr' => $mountName,
                'source_spell_id' => $spellMap[$mount['id']] ?? null,
                'is_active' => true,
            ]);
        }

        if ($skipped > 0) {
            $this->info(sprintf('  Skipped %d mounts with empty names.', $skipped));
        }

        $this->info('Mount import complete.');
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
