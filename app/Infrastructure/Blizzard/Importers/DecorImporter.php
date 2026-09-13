<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyReader;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowDecor;

/**
 * Catalogue des décors : l'API tranche l'existence, la taxonomie curée tranche le rangement.
 *
 * Voir MountImporter pour le détail du partage d'autorité.
 *
 * Les décors marqués notObtainable par SimpleArmory restent importés mais inactifs :
 * 119 d'entre eux figurent dans l'index API live — l'API atteste qu'ils existent, jamais
 * qu'ils sont encore obtenables (événement de pré-lancement clos, promotion retirée).
 * Sans ce flag ils compteraient au dénominateur et rendraient le 100 % inatteignable.
 * Un décor que le fichier curé ne connaît pas entre donc actif : rien n'atteste le contraire.
 */
final readonly class DecorImporter
{
    use ImportsFromBlizzardApi;

    public function __construct(
        BlizzardApiClient $blizzardApiClient,
        private CollectionTaxonomyReader $collectionTaxonomyReader,
    ) {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    public function import(): void
    {
        $saDecors = $this->loadSimpleArmoryData();
        if ($saDecors === []) {
            return;
        }

        $frenchNames = $this->loadFrenchNames();
        if ($frenchNames === []) {
            return;
        }

        $rows = $this->buildRows($saDecors, $frenchNames, $this->collectionTaxonomyReader->for(CollectionEntity::Decor));

        $this->saveRows($rows);
    }

    /**
     * @return array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null, notObtainable: bool}>
     */
    private function loadSimpleArmoryData(): array
    {
        $this->info('Parsing SimpleArmory decors.json...');

        $decors = SimpleArmoryParser::parseCollection('decors.json');
        if ($decors === []) {
            $this->info('ERROR: Could not parse decors.json.');

            return [];
        }

        $this->info(sprintf('  Found %d decors.', count($decors)));

        return $decors;
    }

    /**
     * Noms français depuis l'index Housing Decor de l'API officielle.
     *
     * @return array<int, string>
     */
    private function loadFrenchNames(): array
    {
        $this->info('Fetching decor index from Blizzard API...');

        $index = $this->fetchWithRetry('data/wow/decor/index');
        if ($index === null) {
            $this->info('  ERROR: decor index unavailable, aborting import (catalog left untouched).');

            return [];
        }

        $names = [];

        /** @var list<array{id?: int, name?: string}> $decorItems */
        $decorItems = $index['decor_items'] ?? [];
        foreach ($decorItems as $decorItem) {
            $id = (int) ($decorItem['id'] ?? 0);
            $name = trim($decorItem['name'] ?? '');
            if ($id > 0 && $name !== '') {
                $names[$id] = $name;
            }
        }

        if ($names === []) {
            $this->info('  ERROR: decor index holds no usable name, aborting import (catalog left untouched).');

            return [];
        }

        $this->info(sprintf('  Found %d live decors in the API index.', count($names)));

        return $names;
    }

    /**
     * @param  array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null, notObtainable: bool}>  $saDecors
     * @param  array<int, string>  $frenchNames
     * @param  array<int, TaxonomyEntry>  $taxonomy
     * @return list<array{id: int, name_fr: string, category: string|null, source: string|null, item_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $saDecors, array $frenchNames, array $taxonomy): array
    {
        $rows = [];
        $awaitingArbitration = 0;
        $inactive = 0;

        foreach ($frenchNames as $id => $nameFr) {
            $entry = $taxonomy[$id] ?? null;
            if (! $entry instanceof TaxonomyEntry) {
                $awaitingArbitration++;
            }

            $decor = $saDecors[$id] ?? null;

            $isActive = $decor === null || ! $decor['notObtainable'];
            if (! $isActive) {
                $inactive++;
            }

            $iconUrl = $decor !== null && $decor['icon'] !== null
                ? SimpleArmoryParser::buildIconUrl($decor['icon'])
                : null;

            $rows[] = [
                'id' => $id,
                'name_fr' => $nameFr,
                'category' => $entry?->category,
                'source' => $entry?->source,
                'item_id' => $decor['itemId'] ?? null,
                'icon_url' => $iconUrl,
                'is_active' => $isActive,
            ];
        }

        $notLive = count(array_diff_key($saDecors, $frenchNames));

        $this->info(sprintf('  %d decors in catalog, %d not obtainable.', count($rows), $inactive));
        $this->info(sprintf('  %d skipped (not in live API index), %d awaiting arbitration (absent from the taxonomy).', $notLive, $awaitingArbitration));

        return $rows;
    }

    /**
     * @param  list<array{id: int, name_fr: string, category: string|null, source: string|null, item_id: int|null, icon_url: string|null, is_active: bool}>  $rows
     */
    private function saveRows(array $rows): void
    {
        $this->info(sprintf('Saving %d decors...', count($rows)));

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            WowDecor::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'category', 'source', 'item_id', 'icon_url', 'is_active'],
            );
            $count += count($chunk);
            $this->info(sprintf('  Saved %d...', $count));
        }

        $this->deleteRowsOutsideCatalog(WowDecor::class, array_column($rows, 'id'), 'decors');

        $this->info(sprintf('Decor import complete: %d items (%d active).', $count, count(array_filter($rows, fn (array $r): bool => $r['is_active']))));
    }
}
