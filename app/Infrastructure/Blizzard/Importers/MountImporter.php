<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowMount;

/**
 * Catalogue des montures = index de l'API officielle ∩ liste curée SimpleArmory.
 *
 * L'API fait autorité sur l'existence et le nom : elle n'expose que ce qui est live
 * sur retail, là où SimpleArmory (construit sur les DB2 dataminés du client) référence
 * déjà le contenu des patchs à venir. SimpleArmory fait autorité sur la présentation —
 * extension, source fine, icône — que l'API n'expose pas.
 *
 * Une monture absente de l'API n'est pas encore obtenable ; une monture absente de
 * SimpleArmory est une entrée non curée (doublon, variante PNJ, nom vide). Les deux
 * sont exclues du catalogue.
 */
final readonly class MountImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    public function import(): void
    {
        $saMounts = $this->loadSimpleArmoryData();
        if ($saMounts === []) {
            return;
        }

        $frenchNames = $this->loadFrenchNames();
        if ($frenchNames === []) {
            return;
        }

        $rows = $this->buildRows($saMounts, $frenchNames);

        $this->saveRows($rows);
    }

    /**
     * @return array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null}>
     */
    private function loadSimpleArmoryData(): array
    {
        $this->info('Parsing SimpleArmory mounts.json...');

        $mounts = SimpleArmoryParser::parseCollection('mounts.json');
        if ($mounts === []) {
            $this->info('ERROR: Could not parse mounts.json.');

            return [];
        }

        $factionCount = count(array_filter($mounts, static fn (array $m): bool => $m['faction'] !== null));
        $this->info(sprintf('  Found %d mounts (%d faction-specific).', count($mounts), $factionCount));

        return $mounts;
    }

    /**
     * Noms français depuis l'index Mount de l'API officielle.
     *
     * @return array<int, string>
     */
    private function loadFrenchNames(): array
    {
        $this->info('Fetching mount index from Blizzard API...');

        $index = $this->fetchWithRetry('data/wow/mount/index');
        if ($index === null) {
            $this->info('  ERROR: mount index unavailable, aborting import (catalog left untouched).');

            return [];
        }

        $names = [];

        /** @var list<array{id?: int, name?: string}> $mounts */
        $mounts = $index['mounts'] ?? [];
        foreach ($mounts as $mount) {
            $id = (int) ($mount['id'] ?? 0);
            $name = trim($mount['name'] ?? '');
            if ($id > 0 && $name !== '') {
                $names[$id] = $name;
            }
        }

        if ($names === []) {
            $this->info('  ERROR: mount index holds no usable name, aborting import (catalog left untouched).');

            return [];
        }

        $this->info(sprintf('  Found %d live mounts in the API index.', count($names)));

        return $names;
    }

    /**
     * @param  array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null}>  $saMounts
     * @param  array<int, string>  $frenchNames
     * @return list<array{id: int, name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $saMounts, array $frenchNames): array
    {
        $rows = [];
        $notCurated = 0;
        $withIcons = 0;

        foreach ($frenchNames as $id => $nameFr) {
            $mount = $saMounts[$id] ?? null;
            if ($mount === null) {
                $notCurated++;

                continue;
            }

            $iconUrl = $mount['icon'] !== null ? SimpleArmoryParser::buildIconUrl($mount['icon']) : null;
            if ($iconUrl !== null) {
                $withIcons++;
            }

            $rows[] = [
                'id' => $id,
                'name_fr' => $nameFr,
                'source' => $mount['source'] !== '' ? $mount['source'] : null,
                'category' => $mount['category'] !== '' ? $mount['category'] : null,
                'source_spell_id' => $mount['spellid'] > 0 ? $mount['spellid'] : null,
                'icon_url' => $iconUrl,
                'is_active' => true,
            ];
        }

        $notLive = count(array_diff_key($saMounts, $frenchNames));

        $this->info(sprintf('  %d mounts in catalog, %d with icon URL.', count($rows), $withIcons));
        $this->info(sprintf('  %d skipped (not in live API index), %d skipped (not curated by SimpleArmory).', $notLive, $notCurated));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>  $rows
     */
    private function saveRows(array $rows): void
    {
        $this->info(sprintf('Saving %d mounts...', count($rows)));

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            WowMount::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'source', 'category', 'source_spell_id', 'icon_url', 'is_active'],
            );
            $count += count($chunk);
            $this->info(sprintf('  Saved %d...', $count));
        }

        $this->deleteRowsOutsideCatalog(WowMount::class, array_column($rows, 'id'), 'mounts');

        $this->info(sprintf('Mount import complete: %d items.', $count));
    }
}
