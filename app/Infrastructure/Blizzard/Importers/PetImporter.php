<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowPet;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

final class PetImporter
{
    /**
     * French spell name prefixes to strip when deriving pet names.
     *
     * @var list<string>
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

    /**
     * @param  array<int, string>  $spellNameMap  [spell_id => spell_name]
     */
    public function import(array $spellNameMap = []): void
    {
        $saPets = $this->loadSimpleArmoryData();
        if ($saPets === []) {
            return;
        }

        $frenchNames = $this->loadFrenchNames($spellNameMap);
        $rows = $this->buildRows($saPets, $frenchNames);

        $this->saveRows($rows);
    }

    /**
     * @return array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null}>
     */
    private function loadSimpleArmoryData(): array
    {
        $this->info('Parsing SimpleArmory pets.json...');

        $pets = SimpleArmoryParser::parseCollection('pets.json');
        if ($pets === []) {
            $this->info('ERROR: Could not parse pets.json.');

            return [];
        }

        $factionCount = count(array_filter($pets, static fn (array $p): bool => $p['faction'] !== null));
        $this->info(sprintf('  Found %d pets (%d faction-specific).', count($pets), $factionCount));

        return $pets;
    }

    /**
     * Build French name map from battle_pet_species.csv (pet_id => french_name).
     * Pet names come from the SummonSpellID's spell name, cleaned of invocation prefixes.
     *
     * @param  array<int, string>  $spellNameMap
     * @return array<int, string>
     */
    private function loadFrenchNames(array $spellNameMap): array
    {
        $this->info('Loading French pet names from battle_pet_species.csv + spell names...');

        $csvPath = storage_path('app/blizzard/battle_pet_species.csv');
        if (! File::exists($csvPath)) {
            $this->info('  WARNING: battle_pet_species.csv not found.');

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
        $spellIdx = (int) array_search('SummonSpellID', $headers, true);

        $names = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $id = (int) $row[$idIdx];
            $spellId = (int) ($row[$spellIdx] ?? 0);
            if ($id <= 0) {
                continue;
            }

            if ($spellId <= 0) {
                continue;
            }

            if (! isset($spellNameMap[$spellId])) {
                continue;
            }

            $name = $this->cleanPetName($spellNameMap[$spellId]);
            if ($name !== '') {
                $names[$id] = $name;
            }
        }

        fclose($handle);

        $this->info(sprintf('  Found %d French pet names.', count($names)));

        return $names;
    }

    /**
     * @param  array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null}>  $saPets
     * @param  array<int, string>  $frenchNames
     * @return list<array{id: int, name_fr: string, category: string|null, source: string|null, creature_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $saPets, array $frenchNames): array
    {
        $rows = [];
        $matched = 0;
        $fallbacks = 0;
        $withIcons = 0;

        foreach ($saPets as $id => $pet) {
            $nameFr = $frenchNames[$id] ?? null;
            if ($nameFr !== null) {
                $matched++;
            } else {
                $fallbacks++;
            }

            $iconUrl = $pet['icon'] !== null ? SimpleArmoryParser::buildIconUrl($pet['icon']) : null;
            if ($iconUrl !== null) {
                $withIcons++;
            }

            $rows[] = [
                'id' => $id,
                'name_fr' => $nameFr ?? sprintf('[EN] Pet #%d', $id),
                'category' => $pet['category'] !== '' ? $pet['category'] : null,
                'source' => $pet['source'] !== '' ? $pet['source'] : null,
                'creature_id' => $pet['creatureId'] > 0 ? $pet['creatureId'] : null,
                'icon_url' => $iconUrl,
                'is_active' => true,
            ];
        }

        $this->info(sprintf('  %d matched with French name, %d using English fallback.', $matched, $fallbacks));
        $this->info(sprintf('  %d with icon URL.', $withIcons));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, category: string|null, source: string|null, creature_id: int|null, icon_url: string|null, is_active: bool}>  $rows
     */
    private function saveRows(array $rows): void
    {
        $this->info(sprintf('Saving %d pets...', count($rows)));

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            WowPet::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'category', 'source', 'creature_id', 'icon_url', 'is_active'],
            );
            $count += count($chunk);
            $this->info(sprintf('  Saved %d...', $count));
        }

        $this->info(sprintf('Pet import complete: %d items.', $count));
    }

    private function cleanPetName(string $spellName): string
    {
        foreach (self::SPELL_NAME_PREFIXES as $prefix) {
            if (str_starts_with($spellName, $prefix)) {
                return mb_substr($spellName, mb_strlen($prefix));
            }
        }

        return $spellName;
    }

    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message.PHP_EOL;
        }

        Log::info($message);
    }
}
