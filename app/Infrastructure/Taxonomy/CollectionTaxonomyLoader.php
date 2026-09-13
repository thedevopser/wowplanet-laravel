<?php

declare(strict_types=1);

namespace App\Infrastructure\Taxonomy;

use App\Infrastructure\Parsers\SimpleArmoryParser;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Models\WowCollectionTaxonomy;

/**
 * Amorçage et enrichissement de la taxonomie depuis les fichiers curés de SimpleArmory.
 *
 * Le chargement est strictement additif : `insertOrIgnore` laisse en place toute ligne déjà
 * connue, si bien que la première exécution amorce et que les suivantes ne font qu'ajouter les
 * entrées d'un nouveau patch. C'est ce qui permet à un arbitrage manuel de survivre.
 *
 * Le dédoublonnage est délégué à `SimpleArmoryParser`, qui garde la dernière occurrence d'un
 * identifiant listé sous plusieurs catégories. C'est ce qu'a fait chaque import jusqu'ici, donc
 * ce qui a produit le rangement actuel : insérer la première occurrence ferait dériver plusieurs
 * dizaines d'entrées par rapport au catalogue en place.
 */
final readonly class CollectionTaxonomyLoader
{
    private const CHUNK_SIZE = 500;

    /**
     * @return array{read: int, inserted: int, skipped: int}
     */
    public function load(CollectionEntity $collectionEntity): array
    {
        $curated = SimpleArmoryParser::parseCollection($collectionEntity->simpleArmoryFile());

        if ($curated === []) {
            throw TaxonomySourceUnavailableException::for($collectionEntity->simpleArmoryFile());
        }

        $rows = $this->rows($collectionEntity, $curated);
        $inserted = 0;

        foreach (array_chunk($rows, self::CHUNK_SIZE) as $chunk) {
            $inserted += WowCollectionTaxonomy::query()->insertOrIgnore($chunk);
        }

        return [
            'read' => count($rows),
            'inserted' => $inserted,
            'skipped' => count($rows) - $inserted,
        ];
    }

    /**
     * @param  array<int, array{category: string, source: string, icon: string|null, faction: string|null, spellid: int, creatureId: int, itemId: int|null, notObtainable: bool}>  $curated
     * @return list<array{entity: string, entry_id: int, category: string|null, source: string|null}>
     */
    private function rows(CollectionEntity $collectionEntity, array $curated): array
    {
        $rows = [];

        foreach ($curated as $entryId => $entry) {
            $rows[] = [
                'entity' => $collectionEntity->value,
                'entry_id' => $entryId,
                'category' => $entry['category'] !== '' ? $entry['category'] : null,
                'source' => $entry['source'] !== '' ? $entry['source'] : null,
            ];
        }

        return $rows;
    }
}
