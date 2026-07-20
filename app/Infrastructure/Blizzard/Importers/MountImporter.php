<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Models\WowMount;

final readonly class MountImporter
{
    use ImportsFromBlizzardApi;

    private const API_ONLY_CATEGORY = 'Autres';

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
            $this->info('  WARNING: mount index unavailable, falling back to English names.');

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

        $this->info(sprintf('  Found %d French names.', count($names)));

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
        $matched = 0;
        $fallbacks = 0;
        $withIcons = 0;

        foreach ($saMounts as $id => $mount) {
            $nameFr = $frenchNames[$id] ?? null;
            if ($nameFr !== null) {
                $matched++;
            } else {
                $fallbacks++;
            }

            $iconUrl = $mount['icon'] !== null ? SimpleArmoryParser::buildIconUrl($mount['icon']) : null;
            if ($iconUrl !== null) {
                $withIcons++;
            }

            $rows[] = [
                'id' => $id,
                'name_fr' => $nameFr ?? sprintf('[EN] Mount #%d', $id),
                'source' => $mount['source'] !== '' ? $mount['source'] : null,
                'category' => $mount['category'] !== '' ? $mount['category'] : null,
                'source_spell_id' => $mount['spellid'] > 0 ? $mount['spellid'] : null,
                'icon_url' => $iconUrl,
                'is_active' => true,
            ];
        }

        $this->info(sprintf('  %d matched with French name, %d using English fallback.', $matched, $fallbacks));
        $this->info(sprintf('  %d with icon URL.', $withIcons));

        // Complète avec les montures présentes dans l'index API mais absentes de
        // SimpleArmory : SimpleArmory ne liste que la sélection curée, l'API en
        // référence davantage. Ces montures n'ont pas d'extension (l'API ne
        // l'expose pas), on les regroupe donc sous une catégorie « Autres ».
        $apiOnly = 0;
        foreach ($frenchNames as $id => $name) {
            if (isset($saMounts[$id])) {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'name_fr' => $name !== '' ? $name : sprintf('[EN] Mount #%d', $id),
                'source' => null,
                'category' => self::API_ONLY_CATEGORY,
                'source_spell_id' => null,
                'icon_url' => null,
                'is_active' => true,
            ];
            $apiOnly++;
        }

        $this->info(sprintf('  %d API-only mounts added (category "%s").', $apiOnly, self::API_ONLY_CATEGORY));

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

        $this->info(sprintf('Mount import complete: %d items.', $count));
    }
}
