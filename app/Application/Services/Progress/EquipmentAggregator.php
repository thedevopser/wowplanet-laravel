<?php

declare(strict_types=1);

namespace App\Application\Services\Progress;

use App\Infrastructure\Parsers\SimpleArmoryParser;

class EquipmentAggregator
{
    /**
     * @param  array<string, mixed>  $apiResponse
     * @return list<array{slot: string, slot_name: string, item_id: int, name: string, item_level: int, quality: string, icon_url: string|null}>
     */
    public function aggregate(array $apiResponse): array
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
            /** @var array{id?: int, name?: string} $itemData */
            $itemData = $equippedItem['item'] ?? [];
            /** @var array{type?: string} $quality */
            $quality = $equippedItem['quality'] ?? [];
            /** @var array{value?: int} $level */
            $level = $equippedItem['level'] ?? [];
            /** @var array{id?: int} $media */
            $media = $equippedItem['media'] ?? [];

            $mediaId = isset($media['id']) ? (string) $media['id'] : '';
            $iconUrl = SimpleArmoryParser::buildIconUrl($mediaId);

            $result[] = [
                'slot' => (string) ($slot['type'] ?? ''),
                'slot_name' => (string) ($slot['name'] ?? ''),
                'item_id' => (int) ($itemData['id'] ?? 0),
                'name' => (string) ($itemData['name'] ?? ''),
                'item_level' => (int) ($level['value'] ?? 0),
                'quality' => (string) ($quality['type'] ?? 'COMMON'),
                'icon_url' => $iconUrl,
            ];
        }

        return $result;
    }
}
