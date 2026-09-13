<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyReader;
use App\Infrastructure\Taxonomy\TaxonomyEntry;
use App\Models\WowMount;

/**
 * Catalogue des montures : l'API tranche l'existence, la taxonomie curée tranche le rangement.
 *
 * L'API fait autorité sur l'existence et le nom : elle n'expose que ce qui est live
 * sur retail, là où SimpleArmory (construit sur les DB2 dataminés du client) référence
 * déjà le contenu des patchs à venir. Une monture absente de l'API n'est donc pas encore
 * obtenable et reste hors catalogue.
 *
 * La catégorie et la source viennent de `wow_collection_taxonomy`, plus du fichier curé.
 * Une monture que la taxonomie ne range pas entre quand même, sans rangement, et figure au
 * rapport d'entrées à arbitrer : l'invisibilité silencieuse était le défaut à corriger.
 *
 * Le fichier curé ne sert plus qu'à l'icône et à l'identifiant de sort, que l'API n'expose
 * pas encore ici. Il reste donc une source requise, et son absence interrompt l'import
 * plutôt que d'effacer les icônes de tout le catalogue.
 */
final readonly class MountImporter
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
        $saMounts = $this->loadSimpleArmoryData();
        if ($saMounts === []) {
            return;
        }

        $frenchNames = $this->loadFrenchNames();
        if ($frenchNames === []) {
            return;
        }

        $rows = $this->buildRows($saMounts, $frenchNames, $this->collectionTaxonomyReader->for(CollectionEntity::Mount));

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
     * @param  array<int, TaxonomyEntry>  $taxonomy
     * @return list<array{id: int, name_fr: string, source: string|null, category: string|null, source_spell_id: int|null, icon_url: string|null, is_active: bool}>
     */
    private function buildRows(array $saMounts, array $frenchNames, array $taxonomy): array
    {
        $rows = [];
        $awaitingArbitration = 0;
        $withIcons = 0;

        foreach ($frenchNames as $id => $nameFr) {
            $entry = $taxonomy[$id] ?? null;
            if (! $entry instanceof TaxonomyEntry) {
                $awaitingArbitration++;
            }

            $mount = $saMounts[$id] ?? null;

            $iconUrl = $mount !== null && $mount['icon'] !== null
                ? SimpleArmoryParser::buildIconUrl($mount['icon'])
                : null;
            if ($iconUrl !== null) {
                $withIcons++;
            }

            $rows[] = [
                'id' => $id,
                'name_fr' => $nameFr,
                'source' => $entry?->source,
                'category' => $entry?->category,
                'source_spell_id' => $mount !== null && $mount['spellid'] > 0 ? $mount['spellid'] : null,
                'icon_url' => $iconUrl,
                'is_active' => true,
            ];
        }

        $notLive = count(array_diff_key($saMounts, $frenchNames));

        $this->info(sprintf('  %d mounts in catalog, %d with icon URL.', count($rows), $withIcons));
        $this->info(sprintf('  %d skipped (not in live API index), %d awaiting arbitration (absent from the taxonomy).', $notLive, $awaitingArbitration));

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
