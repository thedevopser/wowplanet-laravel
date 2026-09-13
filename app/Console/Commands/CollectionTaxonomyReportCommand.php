<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Taxonomy\CollectionEntity;
use App\Models\WowCollectionTaxonomy;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Liste les entrées de catalogue que la taxonomie ne range pas encore.
 *
 * Le rapport n'est pas stocké : l'absence de ligne de taxonomie pour une ligne de catalogue
 * *est* le rapport, et une jointure gauche le reconstitue à tout moment. Une entrée rangée
 * nulle part **en connaissance de cause** porte, elle, une ligne de taxonomie aux deux
 * libellés nuls : elle est curée, donc hors de ce rapport.
 */
class CollectionTaxonomyReportCommand extends Command
{
    protected $signature = 'app:collection-taxonomy-report
        {--entity= : Ne rapporter qu\'une seule collection}
        {--limit=20 : Nombre d\'entrées détaillées par collection}';

    protected $description = 'Liste les entrées de collection à arbitrer dans la taxonomie';

    public function handle(): int
    {
        try {
            $entities = $this->entitiesToReport();
        } catch (InvalidArgumentException $invalidArgumentException) {
            $this->error($invalidArgumentException->getMessage());

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));

        $this->info('Entrées à arbitrer');
        $this->newLine();

        foreach ($entities as $entity) {
            $this->reportEntity($entity, $limit);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<CollectionEntity>
     */
    private function entitiesToReport(): array
    {
        /** @var string|null $requested */
        $requested = $this->option('entity');

        if ($requested === null) {
            return CollectionEntity::cases();
        }

        return [CollectionEntity::fromOption($requested)];
    }

    private function reportEntity(CollectionEntity $collectionEntity, int $limit): void
    {
        $pending = $this->pendingEntries($collectionEntity);

        if ($pending === []) {
            $this->line(sprintf('  %-8s Rien à arbitrer', $collectionEntity->value));

            return;
        }

        $this->line(sprintf(
            '  %-8s %d à arbitrer sur %d au catalogue',
            $collectionEntity->value,
            count($pending),
            $this->catalogCount($collectionEntity),
        ));

        foreach (array_slice($pending, 0, $limit) as $entry) {
            $this->line(sprintf('    %8d  %s', $entry['id'], $entry['name_fr']));
        }

        $omitted = count($pending) - $limit;
        if ($omitted > 0) {
            $this->line(sprintf('    … et %d autre(s).', $omitted));
        }

        $this->newLine();
    }

    /**
     * Entrées du catalogue sans ligne de taxonomie, par identifiant croissant.
     *
     * @return list<array{id: int, name_fr: string}>
     */
    private function pendingEntries(CollectionEntity $collectionEntity): array
    {
        $builder = WowCollectionTaxonomy::query()
            ->where('entity', $collectionEntity)
            ->select('entry_id');

        $pending = match ($collectionEntity) {
            CollectionEntity::Mount => WowMount::query()
                ->whereNotIn('id', $builder)
                ->orderBy('id')
                ->get(['id', 'name_fr'])
                ->map(static fn (WowMount $wowMount): array => ['id' => $wowMount->id, 'name_fr' => $wowMount->name_fr])
                ->all(),
            CollectionEntity::Pet => WowPet::query()
                ->whereNotIn('id', $builder)
                ->orderBy('id')
                ->get(['id', 'name_fr'])
                ->map(static fn (WowPet $wowPet): array => ['id' => $wowPet->id, 'name_fr' => $wowPet->name_fr])
                ->all(),
            CollectionEntity::Decor => WowDecor::query()
                ->whereNotIn('id', $builder)
                ->orderBy('id')
                ->get(['id', 'name_fr'])
                ->map(static fn (WowDecor $wowDecor): array => ['id' => $wowDecor->id, 'name_fr' => $wowDecor->name_fr])
                ->all(),
        };

        return array_values($pending);
    }

    private function catalogCount(CollectionEntity $collectionEntity): int
    {
        return match ($collectionEntity) {
            CollectionEntity::Mount => WowMount::query()->count(),
            CollectionEntity::Pet => WowPet::query()->count(),
            CollectionEntity::Decor => WowDecor::query()->count(),
        };
    }
}
