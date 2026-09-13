<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard;

use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\Responses\ItemSearchDocument;
use App\Infrastructure\Blizzard\Responses\MediaSearchDocument;
use App\Infrastructure\Blizzard\Responses\ResponsePayload;
use InvalidArgumentException;

/**
 * Balayage du catalogue d'items par fenêtres d'identifiants.
 *
 * Une fenêtre est un index `w` désignant l'intervalle `[w × 1000, w × 1000 + 999]`. La
 * grille est fixe, et c'est ce qui rend le balayage reprenable : une fenêtre se désigne
 * par un entier, indépendamment de ce qu'elle contient. Mille identifiants tenant
 * toujours sous la page maximale de l'API, une fenêtre ne pagine jamais.
 *
 * Les réponses sont volumineuses — toutes les locales sont servies quelle que soit celle
 * demandée, soit environ 1,2 Mo par fenêtre. Chaque document est réduit à sa forme
 * compacte puis libéré immédiatement ; l'appelant choisit combien de fenêtres il traite
 * d'un coup, ce qui fixe le pic mémoire.
 */
final readonly class ItemSearchSweep
{
    use ImportsFromBlizzardApi;

    public const WINDOW_SIZE = 1000;

    private const ITEM_ENDPOINT = 'data/wow/search/item';

    private const MEDIA_ENDPOINT = 'data/wow/search/media';

    public function __construct(BlizzardApiClient $blizzardApiClient)
    {
        $this->blizzardApiClient = $blizzardApiClient;
    }

    /**
     * Nombre de fenêtres couvrant le catalogue jusqu'à `$highestId` inclus.
     */
    public static function windowCountFor(int $highestId): int
    {
        if ($highestId < 0) {
            throw new InvalidArgumentException(sprintf('A highest item id cannot be negative, got %d.', $highestId));
        }

        return intdiv($highestId, self::WINDOW_SIZE) + 1;
    }

    /**
     * Plus grand identifiant d'item du catalogue, ou `null` si l'API ne l'a pas rendu.
     *
     * C'est la borne du balayage : sans elle, les fenêtres les plus hautes seraient
     * choisies au jugé, et ce sont précisément celles qui portent le contenu récent.
     */
    public function highestItemId(): ?int
    {
        $decoded = $this->fetchWithRetry(self::ITEM_ENDPOINT.'?_pageSize=1&orderby=id:desc');
        if ($decoded === null) {
            return null;
        }

        $results = ResponsePayload::forEndpoint(self::ITEM_ENDPOINT, $decoded)->objectList('results');

        return $results === [] ? null : $results[0]->requiredObject('data')->requiredInt('id');
    }

    /**
     * Balaie les fenêtres demandées et remet chaque document à l'appelant.
     *
     * @param  list<int>  $windows
     * @param  callable(ItemSearchDocument): void  $onDocument
     */
    public function sweepItems(array $windows, callable $onDocument): void
    {
        $this->sweep(self::ITEM_ENDPOINT, $windows, '', function (ResponsePayload $responsePayload) use ($onDocument): void {
            $onDocument(ItemSearchDocument::fromPayload($responsePayload));
        });
    }

    /**
     * Icônes des items des fenêtres demandées, indexées par identifiant de media.
     *
     * @param  list<int>  $windows
     * @return array<int, MediaSearchDocument>
     */
    public function sweepItemMedia(array $windows): array
    {
        $media = [];

        $this->sweep(self::MEDIA_ENDPOINT, $windows, '&tags=item', function (ResponsePayload $responsePayload) use (&$media): void {
            $mediaSearchDocument = MediaSearchDocument::fromPayload($responsePayload);
            $media[$mediaSearchDocument->id] = $mediaSearchDocument;
        });

        return $media;
    }

    /**
     * @param  list<int>  $windows
     * @param  callable(ResponsePayload): void  $onDocument
     */
    private function sweep(string $endpoint, array $windows, string $extraQuery, callable $onDocument): void
    {
        if ($windows === []) {
            return;
        }

        $endpoints = [];
        foreach ($windows as $window) {
            $endpoints[$window] = $this->windowEndpoint($endpoint, $window, $extraQuery);
        }

        $responses = $this->fetchBatchAsync($endpoints);

        // Chaque réponse est libérée dès qu'elle est réduite : garder les corps décodés
        // du lot entier multiplierait le pic mémoire par le nombre de fenêtres.
        foreach (array_keys($responses) as $key) {
            $decoded = $responses[$key];
            unset($responses[$key]);

            if ($decoded === null) {
                continue;
            }

            foreach (ResponsePayload::forEndpoint($endpoint, $decoded)->objectList('results') as $result) {
                $onDocument($result->requiredObject('data'));
            }
        }
    }

    private function windowEndpoint(string $endpoint, int $window, string $extraQuery): string
    {
        return sprintf(
            '%s?_pageSize=%d&orderby=id&id=[%d,%d]%s',
            $endpoint,
            self::WINDOW_SIZE,
            $window * self::WINDOW_SIZE,
            $window * self::WINDOW_SIZE + self::WINDOW_SIZE - 1,
            $extraQuery,
        );
    }
}
