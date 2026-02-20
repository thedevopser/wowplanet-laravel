<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Models\WowPet;
use Illuminate\Support\Facades\File;

class PetImporter
{
    use ImportsFromBlizzardApi;

    /**
     * French spell name prefixes to strip when deriving pet names.
     */
    private const SPELL_NAME_PREFIXES = [
        'Invocation : ',
        'Invoquer ',
        'Invoque un ',
        'Invoque une ',
        "Invoque l'",
        'Invoque le ',
        'Invoque la ',
        'Invoque des ',
    ];

    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
    ) {}

    /**
     * @param  array<int, string>  $spellNameMap  [spell_id => spell_name]
     */
    public function import(array $spellNameMap = []): void
    {
        $this->info('Loading pets from DB2 CSV data...');

        $pets = $this->parsePetCsv($spellNameMap);
        if ($pets === []) {
            $this->info('ERROR: battle_pet_species.csv not found or empty.');

            return;
        }

        $this->info(sprintf('Found %d pets in CSV.', count($pets)));

        $count = 0;
        foreach ($pets as $pet) {
            WowPet::query()->updateOrCreate(['id' => $pet['id']], [
                'name_fr' => $pet['name_fr'],
                'creature_id' => $pet['creature_id'] > 0 ? $pet['creature_id'] : null,
                'is_active' => true,
            ]);
            $count++;
            if ($count % 500 === 0) {
                $this->info(sprintf('  Saved %d...', $count));
            }
        }

        $this->info(sprintf('Pet import complete: %d pets.', $count));
    }

    /**
     * @param  array<int, string>  $spellNameMap
     * @return list<array{id: int, name_fr: string, creature_id: int}>
     */
    private function parsePetCsv(array $spellNameMap): array
    {
        $csvPath = storage_path('app/blizzard/battle_pet_species.csv');
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

        $idIdx = (int) array_search('ID', $headers, true);
        $creatureIdx = (int) array_search('CreatureID', $headers, true);
        $spellIdx = (int) array_search('SummonSpellID', $headers, true);

        $pets = [];
        $noName = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $id = (int) $row[$idIdx];
            $creatureId = (int) $row[$creatureIdx];
            $spellId = (int) ($row[$spellIdx] ?? 0);

            // Derive name from spell name
            $name = '';
            if ($spellId > 0 && isset($spellNameMap[$spellId])) {
                $name = $this->cleanPetName($spellNameMap[$spellId]);
            }

            if ($name === '') {
                $noName++;

                continue;
            }

            $pets[] = [
                'id' => $id,
                'name_fr' => $name,
                'creature_id' => $creatureId,
            ];
        }

        fclose($handle);

        if ($noName > 0) {
            $this->info(sprintf('  Skipped %d pets without resolvable name.', $noName));
        }

        return $pets;
    }

    /**
     * Strip French invocation prefixes from spell names to get the pet name.
     */
    private function cleanPetName(string $spellName): string
    {
        foreach (self::SPELL_NAME_PREFIXES as $prefix) {
            if (str_starts_with($spellName, $prefix)) {
                return mb_substr($spellName, mb_strlen($prefix));
            }
        }

        return $spellName;
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
