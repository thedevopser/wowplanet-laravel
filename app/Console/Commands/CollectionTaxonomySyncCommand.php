<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Infrastructure\Taxonomy\CollectionTaxonomyLoader;
use App\Infrastructure\Taxonomy\Exceptions\TaxonomySourceUnavailableException;
use App\Models\WowCollectionTaxonomy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Amorce la taxonomie des collections depuis les fichiers curés, puis l'enrichit.
 *
 * La même commande sert aux deux usages : le chargement étant additif, la première exécution
 * amorce une taxonomie vide et les suivantes n'ajoutent que les entrées d'un nouveau patch.
 * Aucune valeur déjà en base n'est réécrite, de sorte qu'un arbitrage manuel survit.
 *
 * Les trois collections sont chargées dans une seule transaction : un fichier curé manquant
 * laisse la taxonomie exactement dans l'état où elle était, plutôt qu'à moitié amorcée.
 */
class CollectionTaxonomySyncCommand extends Command
{
    protected $signature = 'app:collection-taxonomy-sync {--entity= : Synchroniser une seule collection}';

    protected $description = 'Charge la taxonomie curée des montures, mascottes et décorations';

    public function handle(CollectionTaxonomyLoader $collectionTaxonomyLoader): int
    {
        try {
            $entities = $this->entitiesToSync();

            /** @var list<array{entity: CollectionEntity, read: int, inserted: int, skipped: int}> $results */
            $results = DB::transaction(fn (): array => $this->loadAll($collectionTaxonomyLoader, $entities));
        } catch (InvalidArgumentException|TaxonomySourceUnavailableException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->report($results);

        return self::SUCCESS;
    }

    /**
     * @return list<CollectionEntity>
     */
    private function entitiesToSync(): array
    {
        /** @var string|null $requested */
        $requested = $this->option('entity');

        if ($requested === null) {
            return CollectionEntity::cases();
        }

        return [CollectionEntity::fromOption($requested)];
    }

    /**
     * @param  list<CollectionEntity>  $entities
     * @return list<array{entity: CollectionEntity, read: int, inserted: int, skipped: int}>
     */
    private function loadAll(CollectionTaxonomyLoader $collectionTaxonomyLoader, array $entities): array
    {
        $results = [];

        foreach ($entities as $entity) {
            $results[] = ['entity' => $entity, ...$collectionTaxonomyLoader->load($entity)];
        }

        return $results;
    }

    /**
     * @param  list<array{entity: CollectionEntity, read: int, inserted: int, skipped: int}>  $results
     */
    private function report(array $results): void
    {
        $this->info('Taxonomie des collections');
        $this->newLine();

        foreach ($results as $result) {
            $total = WowCollectionTaxonomy::query()->where('entity', $result['entity'])->count();

            $this->line(sprintf(
                '  %-8s %6d curées   %6d en base   %s',
                $result['entity']->value,
                $result['read'],
                $total,
                $result['inserted'] === 0 ? '(=)' : sprintf('(%+d)', $result['inserted']),
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Taxonomie chargée : %d collection(s), %d entrée(s) ajoutée(s).',
            count($results),
            array_sum(array_column($results, 'inserted')),
        ));
    }
}
