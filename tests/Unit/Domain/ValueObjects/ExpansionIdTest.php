<?php

declare(strict_types=1);

use App\Domain\ValueObjects\ExpansionId;

test('it creates valid expansion ids', function (int $value, string $expectedName): void {
    $expansionId = new ExpansionId($value);

    expect($expansionId->value)->toBe($value)
        ->and($expansionId->toString())->toBe($expectedName);
})->with([
    'Classic' => [0, 'Classic'],
    'Burning Crusade' => [1, 'The Burning Crusade'],
    'Wrath of the Lich King' => [2, 'Wrath of the Lich King'],
    'Cataclysm' => [3, 'Cataclysm'],
    'Mists of Pandaria' => [4, 'Mists of Pandaria'],
    'Warlords of Draenor' => [5, 'Warlords of Draenor'],
    'Legion' => [6, 'Legion'],
    'Battle for Azeroth' => [7, 'Battle for Azeroth'],
    'Shadowlands' => [8, 'Shadowlands'],
    'Dragonflight' => [9, 'Dragonflight'],
    'The War Within' => [10, 'The War Within'],
    'Midnight' => [11, 'Midnight'],
]);

test('it rejects invalid values', function (int $value): void {
    expect(fn (): \App\Domain\ValueObjects\ExpansionId => new ExpansionId($value))->toThrow(
        InvalidArgumentException::class,
        'Invalid Expansion ID: '.$value,
    );
})->with([
    'negative' => [-1],
    'too high' => [12],
    'way too high' => [999],
]);

test('it uses correct constants', function (): void {
    expect(ExpansionId::CLASSIC)->toBe(0)
        ->and(ExpansionId::BURNING_CRUSADE)->toBe(1)
        ->and(ExpansionId::THE_WAR_WITHIN)->toBe(10)
        ->and(ExpansionId::MIDNIGHT)->toBe(11);
});
