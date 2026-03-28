<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

class EquipmentAggregator
{
    /**
     * @param  array<string, mixed>  $apiResponse
     * @param  array<int, string>  $iconMap  Map of itemId => iconUrl from Blizzard media API
     * @return list<array{slot: string, slot_name: string, item_id: int, name: string, item_level: int, quality: string, icon_url: string|null}>
     */
    public function aggregate(array $apiResponse, array $iconMap = []): array
    {
        /** @var list<array<string, mixed>> $equippedItems */
        $equippedItems = $apiResponse['equipped_items'] ?? [];

        if ($equippedItems === []) {
            return [];
        }

        $result = [];

        foreach ($equippedItems as $equippedItem) {
            /** @var array{type?: string, name?: string} $slot */
            $slot = $equippedItem['slot'] ?? [];
            /** @var array{id?: int} $itemData */
            $itemData = $equippedItem['item'] ?? [];
            /** @var array{type?: string} $quality */
            $quality = $equippedItem['quality'] ?? [];
            /** @var array{value?: int} $level */
            $level = $equippedItem['level'] ?? [];

            $itemId = (int) ($itemData['id'] ?? 0);
            $iconUrl = $iconMap[$itemId] ?? null;

            $result[] = [
                'slot' => (string) ($slot['type'] ?? ''),
                'slot_name' => (string) ($slot['name'] ?? ''),
                'item_id' => $itemId,
                'name' => is_string($equippedItem['name'] ?? null) ? $equippedItem['name'] : '',
                'item_level' => (int) ($level['value'] ?? 0),
                'quality' => (string) ($quality['type'] ?? 'COMMON'),
                'icon_url' => $iconUrl,
            ];
        }

        return $result;
    }
}
