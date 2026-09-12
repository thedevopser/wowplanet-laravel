<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Responses;

use App\Infrastructure\Blizzard\Responses\Exceptions\MissingFieldException;
use App\Infrastructure\Blizzard\Responses\Exceptions\UnexpectedFieldTypeException;

/**
 * Lecture typée d'une réponse Blizzard décodée.
 *
 * C'est le seul endroit où le `mixed` sorti de `json_decode` est autorisé à vivre :
 * rien n'en ressort qui ne soit typé, et une réponse qui ne respecte pas le contrat
 * échoue ici plutôt que trois couches plus loin.
 */
final readonly class ResponsePayload
{
    /**
     * @param  array<array-key, mixed>  $decoded
     */
    private function __construct(
        private string $endpoint,
        private string $path,
        private array $decoded,
    ) {}

    /**
     * @param  array<array-key, mixed>  $decoded
     */
    public static function forEndpoint(string $endpoint, array $decoded): self
    {
        return new self($endpoint, '', $decoded);
    }

    public function requiredString(string $key): string
    {
        return $this->asString($key, $this->required($key));
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->decoded[$key] ?? null;

        return $value === null ? null : $this->asString($key, $value);
    }

    public function requiredInt(string $key): int
    {
        return $this->asInt($key, $this->required($key));
    }

    public function optionalInt(string $key): ?int
    {
        $value = $this->decoded[$key] ?? null;

        return $value === null ? null : $this->asInt($key, $value);
    }

    public function requiredObject(string $key): self
    {
        return $this->asObject($key, $this->required($key));
    }

    public function optionalObject(string $key): ?self
    {
        $value = $this->decoded[$key] ?? null;

        return $value === null ? null : $this->asObject($key, $value);
    }

    /**
     * Un champ de liste absent rend une liste vide : l'API omet régulièrement les
     * collections vides plutôt que de renvoyer `[]`.
     *
     * @return list<self>
     */
    public function objectList(string $key): array
    {
        $value = $this->decoded[$key] ?? null;
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'list', $value);
        }

        $entries = [];
        foreach ($value as $index => $entry) {
            $entries[] = $this->asObject($key.'.'.$index, $entry);
        }

        return $entries;
    }

    private function required(string $key): mixed
    {
        $value = $this->decoded[$key] ?? null;

        if ($value === null) {
            throw MissingFieldException::at($this->pathTo($key), $this->endpoint);
        }

        return $value;
    }

    private function asString(string $key, mixed $value): string
    {
        if (! is_string($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'string', $value);
        }

        return $value;
    }

    private function asInt(string $key, mixed $value): int
    {
        if (! is_int($value)) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'int', $value);
        }

        return $value;
    }

    private function asObject(string $key, mixed $value): self
    {
        if (! is_array($value) || (array_is_list($value) && $value !== [])) {
            throw UnexpectedFieldTypeException::at($this->pathTo($key), $this->endpoint, 'object', $value);
        }

        return new self($this->endpoint, $this->pathTo($key), $value);
    }

    private function pathTo(string $key): string
    {
        return $this->path === '' ? $key : $this->path.'.'.$key;
    }
}
