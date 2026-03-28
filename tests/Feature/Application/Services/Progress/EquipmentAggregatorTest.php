<?php

declare(strict_types=1);

use App\Application\Services\Progress\EquipmentAggregator;

test('aggregate transforms full API response into structured slot array', function (): void {
    $apiResponse = [
        'equipped_items' => [
            [
                'slot' => ['type' => 'HEAD', 'name' => 'Tête'],
                'item' => ['id' => 12345, 'name' => 'Casque du Néant'],
                'quality' => ['type' => 'EPIC', 'name' => 'Épique'],
                'level' => ['value' => 639],
                'media' => ['id' => 123456],
            ],
            [
                'slot' => ['type' => 'MAIN_HAND', 'name' => 'Main droite'],
                'item' => ['id' => 99999, 'name' => 'Épée légendaire'],
                'quality' => ['type' => 'LEGENDARY', 'name' => 'Légendaire'],
                'level' => ['value' => 645],
                'media' => ['id' => 654321],
            ],
        ],
    ];

    $aggregator = new EquipmentAggregator;
    $result = $aggregator->aggregate($apiResponse);

    expect($result)->toHaveCount(2);

    $head = collect($result)->firstWhere('slot', 'HEAD');
    expect($head['slot'])->toBe('HEAD')
        ->and($head['slot_name'])->toBe('Tête')
        ->and($head['item_id'])->toBe(12345)
        ->and($head['name'])->toBe('Casque du Néant')
        ->and($head['item_level'])->toBe(639)
        ->and($head['quality'])->toBe('EPIC')
        ->and($head['icon_url'])->toBe('https://wow.zamimg.com/images/wow/icons/medium/123456.jpg');

    $weapon = collect($result)->firstWhere('slot', 'MAIN_HAND');
    expect($weapon['quality'])->toBe('LEGENDARY')
        ->and($weapon['item_level'])->toBe(645);
});

test('aggregate handles empty response', function (): void {
    $aggregator = new EquipmentAggregator;

    expect($aggregator->aggregate([]))->toBe([])
        ->and($aggregator->aggregate(['equipped_items' => []]))->toBe([]);
});

test('aggregate handles missing media gracefully', function (): void {
    $apiResponse = [
        'equipped_items' => [
            [
                'slot' => ['type' => 'SHIRT', 'name' => 'Chemise'],
                'item' => ['id' => 111, 'name' => 'Chemise simple'],
                'quality' => ['type' => 'COMMON', 'name' => 'Commun'],
                'level' => ['value' => 1],
            ],
        ],
    ];

    $aggregator = new EquipmentAggregator;
    $result = $aggregator->aggregate($apiResponse);

    expect($result)->toHaveCount(1)
        ->and($result[0]['icon_url'])->toBeNull()
        ->and($result[0]['quality'])->toBe('COMMON');
});

test('aggregate builds icon_url from media id', function (): void {
    $apiResponse = [
        'equipped_items' => [
            [
                'slot' => ['type' => 'CHEST', 'name' => 'Torse'],
                'item' => ['id' => 555, 'name' => 'Plastron'],
                'quality' => ['type' => 'RARE', 'name' => 'Rare'],
                'level' => ['value' => 600],
                'media' => ['id' => 987654],
            ],
        ],
    ];

    $aggregator = new EquipmentAggregator;
    $result = $aggregator->aggregate($apiResponse);

    expect($result[0]['icon_url'])->toBe('https://wow.zamimg.com/images/wow/icons/medium/987654.jpg');
});
