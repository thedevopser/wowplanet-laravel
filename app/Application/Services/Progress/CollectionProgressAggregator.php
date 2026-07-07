<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Models\WowAppearance;
use App\Models\WowDecor;
use App\Models\WowMount;
use App\Models\WowPet;

class CollectionProgressAggregator
{
    /**
     * @param  list<int>  $characterMountIds
     * @return list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>
     */
    public function aggregateMounts(array $characterMountIds): array
    {
        $result = [];
        foreach (WowMount::all() as $mount) {
            $result[] = [
                'id' => $mount->id,
                'name' => $mount->name_fr,
                'is_completed' => in_array($mount->id, $characterMountIds),
                'source' => $mount->source ?? null,
                'category' => $mount->category ?? null,
                'wowhead_id' => $mount->source_spell_id,
                'icon_url' => $mount->icon_url ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $characterPetIds
     * @return list<array{id: int, name: string, is_completed: bool, source: string|null, category: string|null, wowhead_id: int|null, icon_url: string|null}>
     */
    public function aggregatePets(array $characterPetIds): array
    {
        $result = [];
        foreach (WowPet::all() as $pet) {
            $result[] = [
                'id' => $pet->id,
                'name' => $pet->name_fr,
                'is_completed' => in_array($pet->id, $characterPetIds),
                'source' => $pet->source ?? null,
                'category' => $pet->category ?? null,
                'wowhead_id' => $pet->creature_id,
                'icon_url' => $pet->icon_url ?? null,
            ];
        }

        return $result;
    }

    /**
     * @param  list<int>  $characterDecorIds
     * @return list<array{id: int, name: string, is_completed: bool, item_id: int|null, icon_url: string|null, category: string|null, source: string|null}>
     */
    public function aggregateDecor(array $characterDecorIds): array
    {
        $result = [];
        $decors = WowDecor::query()
            ->where('is_active', true)
            ->orWhereIn('id', $characterDecorIds)
            ->get();

        foreach ($decors as $decor) {
            $result[] = [
                'id' => $decor->id,
                'name' => $decor->name_fr,
                'is_completed' => in_array($decor->id, $characterDecorIds),
                'item_id' => $decor->item_id,
                'icon_url' => $decor->icon_url,
                'category' => $decor->category,
                'source' => $decor->source,
            ];
        }

        return $result;
    }

    /**
     * Ventile la garde-robe par slot : débloqué/total. Agrégation SQL (jamais de chargement
     * complet du référentiel en mémoire, cf. volumétrie transmog).
     *
     * @param  list<int>  $characterAppearanceIds
     * @return list<array{slot: string, category: string|null, total: int, completed: int}>
     */
    public function aggregateAppearances(array $characterAppearanceIds): array
    {
        /** @var \Illuminate\Support\Collection<int, object{slot: string, category: string|null, total: int}> $totals */
        $totals = WowAppearance::query()->where('is_active', true)
            ->whereNotNull('slot')
            ->selectRaw('slot, category, COUNT(*) as total')
            ->groupBy('slot', 'category')
            ->orderBy('slot')
            ->get();

        /** @var array<string, int> $completedBySlot */
        $completedBySlot = [];
        if ($characterAppearanceIds !== []) {
            /** @var \Illuminate\Support\Collection<int, object{slot: string, completed: int}> $completedRows */
            $completedRows = WowAppearance::query()->where('is_active', true)
                ->whereNotNull('slot')
                ->whereIn('id', $characterAppearanceIds)
                ->selectRaw('slot, COUNT(*) as completed')
                ->groupBy('slot')
                ->get();

            foreach ($completedRows as $completedRow) {
                $completedBySlot[$completedRow->slot] = $completedRow->completed;
            }
        }

        $result = [];
        foreach ($totals as $total) {
            $result[] = [
                'slot' => $total->slot,
                'category' => $total->category,
                'total' => (int) $total->total,
                'completed' => $completedBySlot[$total->slot] ?? 0,
            ];
        }

        return $result;
    }
}
