<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowPet;

final readonly class PetImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    public function import(): void
    {
        $saPets = $this->loadSimpleArmoryData();
        if ($saPets === []) {
            return;
        }

        $frenchNames = $this->loadFrenchNames();
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
     * Noms français depuis l'index Pet de l'API officielle (id = species id).
     *
     * @return array<int, string>
     */
    private function loadFrenchNames(): array
    {
        $this->info('Fetching pet index from Blizzard API...');

        $index = $this->fetchWithRetry('data/wow/pet/index');
        if ($index === null) {
            $this->info('  WARNING: pet index unavailable, falling back to English names.');

            return [];
        }

        $names = [];

        /** @var list<array{id?: int, name?: string}> $pets */
        $pets = $index['pets'] ?? [];
        foreach ($pets as $pet) {
            $id = (int) ($pet['id'] ?? 0);
            $name = trim($pet['name'] ?? '');
            if ($id > 0 && $name !== '') {
                $names[$id] = $name;
            }
        }

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
}
