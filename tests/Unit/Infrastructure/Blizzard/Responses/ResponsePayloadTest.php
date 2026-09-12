<?php

declare(strict_types=1);

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\Exceptions\UnexpectedFieldTypeException;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;

/**
 * @param  array<string, mixed>  $decoded
 */
function blizzardPayload(array $decoded): ResponsePayload
{
    return ResponsePayload::forEndpoint('data/wow/pvp-season/index', $decoded);
}

// ─── strings ────────────────────────────────────────────────

test('requiredString returns the value', function (): void {
    expect(blizzardPayload(['name' => 'Thrall'])->requiredString('name'))->toBe('Thrall');
});

test('requiredString rejects a missing field', function (): void {
    blizzardPayload([])->requiredString('name');
})->throws(MissingFieldException::class);

test('requiredString rejects a null field', function (): void {
    blizzardPayload(['name' => null])->requiredString('name');
})->throws(MissingFieldException::class);

test('requiredString rejects a field that is not a string', function (): void {
    blizzardPayload(['name' => 42])->requiredString('name');
})->throws(UnexpectedFieldTypeException::class);

test('optionalString returns the value', function (): void {
    expect(blizzardPayload(['name' => 'Thrall'])->optionalString('name'))->toBe('Thrall');
});

test('optionalString returns null on a missing field', function (): void {
    expect(blizzardPayload([])->optionalString('name'))->toBeNull();
});

test('optionalString returns null on a null field', function (): void {
    expect(blizzardPayload(['name' => null])->optionalString('name'))->toBeNull();
});

test('optionalString rejects a field that is not a string', function (): void {
    blizzardPayload(['name' => 42])->optionalString('name');
})->throws(UnexpectedFieldTypeException::class);

// ─── integers ───────────────────────────────────────────────

test('requiredInt returns the value', function (): void {
    expect(blizzardPayload(['id' => 14])->requiredInt('id'))->toBe(14);
});

test('requiredInt rejects a missing field', function (): void {
    blizzardPayload([])->requiredInt('id');
})->throws(MissingFieldException::class);

test('requiredInt rejects a numeric string', function (): void {
    blizzardPayload(['id' => '14'])->requiredInt('id');
})->throws(UnexpectedFieldTypeException::class);

test('requiredInt rejects a boolean', function (): void {
    blizzardPayload(['id' => true])->requiredInt('id');
})->throws(UnexpectedFieldTypeException::class);

test('optionalInt returns the value', function (): void {
    expect(blizzardPayload(['id' => 14])->optionalInt('id'))->toBe(14);
});

test('optionalInt returns null on a missing field', function (): void {
    expect(blizzardPayload([])->optionalInt('id'))->toBeNull();
});

test('optionalInt returns null on a null field', function (): void {
    expect(blizzardPayload(['id' => null])->optionalInt('id'))->toBeNull();
});

test('optionalInt rejects a field that is not an integer', function (): void {
    blizzardPayload(['id' => 1.5])->optionalInt('id');
})->throws(UnexpectedFieldTypeException::class);

// ─── nested objects ─────────────────────────────────────────

test('requiredObject returns a reader over the nested payload', function (): void {
    $responsePayload = blizzardPayload(['current_season' => ['id' => 40]])->requiredObject('current_season');

    expect($responsePayload->requiredInt('id'))->toBe(40);
});

test('requiredObject rejects a missing field', function (): void {
    blizzardPayload([])->requiredObject('current_season');
})->throws(MissingFieldException::class);

test('requiredObject rejects a field that is not an object', function (): void {
    blizzardPayload(['current_season' => 40])->requiredObject('current_season');
})->throws(UnexpectedFieldTypeException::class);

test('requiredObject rejects a list', function (): void {
    blizzardPayload(['seasons' => [['id' => 40]]])->requiredObject('seasons');
})->throws(UnexpectedFieldTypeException::class);

test('optionalObject returns a reader over the nested payload', function (): void {
    $season = blizzardPayload(['current_season' => ['id' => 40]])->optionalObject('current_season');

    expect($season?->requiredInt('id'))->toBe(40);
});

test('optionalObject returns null on a missing field', function (): void {
    expect(blizzardPayload([])->optionalObject('current_season'))->toBeNull();
});

test('optionalObject returns null on a null field', function (): void {
    expect(blizzardPayload(['current_season' => null])->optionalObject('current_season'))->toBeNull();
});

test('optionalObject rejects a field that is not an object', function (): void {
    blizzardPayload(['current_season' => 'now'])->optionalObject('current_season');
})->throws(UnexpectedFieldTypeException::class);

test('an empty object is a valid object', function (): void {
    expect(blizzardPayload(['current_season' => []])->requiredObject('current_season')->optionalInt('id'))->toBeNull();
});

// ─── object lists ───────────────────────────────────────────

test('objectList returns one reader per entry', function (): void {
    $seasons = blizzardPayload(['seasons' => [['id' => 39], ['id' => 40]]])->objectList('seasons');

    expect($seasons)->toHaveCount(2)
        ->and($seasons[0]->requiredInt('id'))->toBe(39)
        ->and($seasons[1]->requiredInt('id'))->toBe(40);
});

test('objectList returns an empty list on a missing field', function (): void {
    expect(blizzardPayload([])->objectList('seasons'))->toBe([]);
});

test('objectList returns an empty list on a null field', function (): void {
    expect(blizzardPayload(['seasons' => null])->objectList('seasons'))->toBe([]);
});

test('objectList rejects a field that is not a list', function (): void {
    blizzardPayload(['seasons' => ['id' => 40]])->objectList('seasons');
})->throws(UnexpectedFieldTypeException::class);

test('objectList rejects an entry that is not an object', function (): void {
    blizzardPayload(['seasons' => [40]])->objectList('seasons');
})->throws(UnexpectedFieldTypeException::class);

// ─── error messages ─────────────────────────────────────────

test('a missing field names the field and the endpoint', function (): void {
    blizzardPayload([])->requiredInt('id');
})->throws(MissingFieldException::class, 'Missing field [id] in the response of [data/wow/pvp-season/index]');

test('a missing nested field names the full path', function (): void {
    blizzardPayload(['current_season' => []])->requiredObject('current_season')->requiredInt('id');
})->throws(MissingFieldException::class, 'Missing field [current_season.id] in the response of [data/wow/pvp-season/index]');

test('a missing field inside a list entry names its index', function (): void {
    blizzardPayload(['seasons' => [['id' => 39], []]])->objectList('seasons')[1]->requiredInt('id');
})->throws(MissingFieldException::class, 'Missing field [seasons.1.id] in the response of [data/wow/pvp-season/index]');

test('an unexpected type names the expected and the received type', function (): void {
    blizzardPayload(['current_season' => ['id' => '40']])->requiredObject('current_season')->requiredInt('id');
})->throws(UnexpectedFieldTypeException::class, 'Field [current_season.id] in the response of [data/wow/pvp-season/index] should be of type int, string given');
