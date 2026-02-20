<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;
use Illuminate\Support\Collection;

class CollectionProgressAggregator
{
    /**
     * @param  list<int>  $characterMountIds
     * @return list<array{id: int, name: string, is_completed: bool, source: string|null, wowhead_id: int|null, icon_url: string|null}>
     */
    public function aggregateMounts(array $characterMountIds): array
    {
        return $this->processCollection(WowMount::all(), $characterMountIds, fn (WowMount $wowMount) => $wowMount->source_spell_id); // @phpstan-ignore argument.type
    }

    /**
     * @param  list<int>  $characterPetIds
     * @return list<array{id: int, name: string, is_completed: bool, source: string|null, wowhead_id: int|null, icon_url: string|null}>
     */
    public function aggregatePets(array $characterPetIds): array
    {
        return $this->processCollection(WowPet::all(), $characterPetIds, fn (WowPet $wowPet) => $wowPet->creature_id); // @phpstan-ignore argument.type
    }

    /**
     * @param  list<int>  $characterDecorIds
     * @return list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null}>
     */
    public function aggregateDecor(array $characterDecorIds): array
    {
        $result = [];
        foreach (WowDecor::all() as $item) {
            $result[] = [
                'id' => $item->id,
                'name' => $item->name_fr,
                'is_completed' => in_array($item->id, $characterDecorIds),
                'item_id' => $item->item_id,
                'icon_url' => $item->icon_url,
            ];
        }

        return $result;
    }

    /**
     * @param  Collection<int, WowMount|WowPet>  $allItems
     * @param  list<int>  $characterIds
     * @return list<array{id: int, name: string, is_completed: bool, source: string|null, wowhead_id: int|null, icon_url: string|null}>
     */
    private function processCollection(Collection $allItems, array $characterIds, \Closure $wowheadIdResolver): array
    {
        $result = [];
        foreach ($allItems as $allItem) {
            /** @var int|null $wowheadId */
            $wowheadId = $wowheadIdResolver($allItem);
            $result[] = [
                'id' => $allItem->id,
                'name' => $allItem->name_fr,
                'is_completed' => in_array($allItem->id, $characterIds),
                'source' => $allItem->source ?? null,
                'wowhead_id' => $wowheadId,
                'icon_url' => $allItem->icon_url ?? null,
            ];
        }

        return $result;
    }
}
