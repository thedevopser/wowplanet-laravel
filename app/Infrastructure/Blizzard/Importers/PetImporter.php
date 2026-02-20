<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Support\Db2CsvLoader;
use App\Models\WowPet;

class PetImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    public function import(): void
    {
        $this->info('Fetching Pet Index...');
        $response = $this->fetchWithRetry('data/wow/pet/index');
        if (! $response) {
            $this->info('ERROR: Could not fetch pet index.');

            return;
        }

        /** @var list<array{id: int, name: string|null}> $pets */
        $pets = $response['pets'] ?? [];
        $this->info('Found '.count($pets).' pets.');

        // battle_pet_species.csv: Description_lang(0), SourceText_lang(1), ID(2), CreatureID(3)
        $creatureMap = Db2CsvLoader::loadMap('battle_pet_species.csv', 2, 3);
        $this->info('  DB2 pet creature map: '.count($creatureMap).' entries.');

        $skipped = 0;
        foreach ($pets as $pet) {
            $petName = $pet['name'] ?? '';
            if ($petName === '') {
                $skipped++;

                continue;
            }

            WowPet::query()->updateOrCreate(['id' => $pet['id']], [
                'name_fr' => $petName,
                'creature_id' => $creatureMap[$pet['id']] ?? null,
                'is_active' => true,
            ]);
        }

        if ($skipped > 0) {
            $this->info(sprintf('  Skipped %d pets with empty names.', $skipped));
        }

        $this->info('Pet import complete.');
    }

    public function importIcons(): void
    {
        $this->info('Fetching pet icons...');

        /** @var \Illuminate\Database\Eloquent\Collection<int, WowPet> $pets */
        $pets = WowPet::query()->whereNull('icon_url')->get();
        $this->info(sprintf('  %d pets need icons.', $pets->count()));
        $count = 0;

        foreach ($pets as $pet) {
            $this->delayIconRequest();

            $media = $this->fetchWithRetry('data/wow/media/pet/'.$pet->id);
            if (! $media) {
                continue;
            }

            /** @var list<array{key: string, value: string}> $assets */
            $assets = $media['assets'] ?? [];
            $iconUrl = $assets[0]['value'] ?? null;
            if ($iconUrl) {
                $pet->update(['icon_url' => $iconUrl]);
                $count++;
            }

            if ($count % 100 === 0 && $count > 0) {
                $this->info(sprintf('  Icons fetched: %d...', $count));
            }
        }

        $this->info(sprintf('Pet icon import complete: %d icons.', $count));
    }
}
