<?php

declare(strict_types=1);

namespace App\Infrastructure\Blizzard\Importers;

use App\Infrastructure\Blizzard\BlizzardApiClient;
use App\Infrastructure\Blizzard\Concerns\ImportsFromBlizzardApi;
use App\Infrastructure\Blizzard\HourlyBudgetGuard;
use App\Models\WowAppearance;
use Illuminate\Support\Sleep;

/**
 * Importe la garde-robe depuis l'API officielle Blizzard (Item Appearance API).
 *
 * Pipeline : index par slot (18 appels, liste curée des apparences collectionnables)
 * → détail par apparence (items liés, classe, media) → recherches bulk items (qualités)
 * et media (icônes). Incrémental par défaut : seules les apparences absentes de la
 * base sont détaillées.
 */
final readonly class AppearanceImporter
{
    use ImportsFromBlizzardApi;

    /** Slots transmoggables exposés par l'Item Appearance API. */
    private const API_SLOTS = [
        'HEAD', 'SHOULDER', 'BODY', 'CHEST', 'WAIST', 'LEGS', 'FEET', 'WRIST', 'HAND',
        'CLOAK', 'TABARD', 'WEAPON', 'SHIELD', 'RANGED', 'TWOHWEAPON', 'WEAPONMAINHAND',
        'WEAPONOFFHAND', 'HOLDABLE',
    ];

    /** Alias API → vocabulaire de slots historique de la base (préserve les filtres du front). */
    private const SLOT_ALIASES = [
        'BODY' => 'SHIRT',
        'WEAPONMAINHAND' => 'WEAPON',
    ];

    /** Qualités API → OverallQualityID numérique historique. */
    private const QUALITY_RANKS = [
        'POOR' => 0,
        'COMMON' => 1,
        'UNCOMMON' => 2,
        'RARE' => 3,
        'EPIC' => 4,
        'LEGENDARY' => 5,
        'ARTIFACT' => 6,
        'HEIRLOOM' => 7,
    ];

    /** Largeur des fenêtres d'IDs pour les recherches bulk (≤ 1 000 IDs ⇒ jamais de pagination). */
    private const SEARCH_WINDOW = 1000;

    /** Nombre de détails d'apparences récupérés entre deux contrôles de budget horaire. */
    private const DETAIL_CHUNK = 2000;

    /**
     * Fenêtres de recherche traitées par lot (réponses volumineuses, parsées puis
     * libérées). Un document de recherche d'item pèse ~10 Ko décodé (toutes les
     * locales + preview) : 5 fenêtres × 1 000 documents ≈ 50 Mo de pic mémoire.
     */
    private const SEARCH_BATCH = 5;

    public function __construct(
        private BlizzardApiClient $blizzardApiClient,
        private HourlyBudgetGuard $hourlyBudgetGuard,
    ) {}

    /**
     * @param  int|null  $limit  Borne le nombre de détails récupérés (smoke-test sans consommer le quota API)
     */
    public function import(bool $full = false, ?int $limit = null): void
    {
        $this->info('Fetching item appearance slot indexes from Blizzard API...');

        $slotByAppearance = $this->fetchSlotIndexes();
        if ($slotByAppearance === []) {
            $this->info('ERROR: Could not fetch any appearance slot index.');

            return;
        }

        $this->info(sprintf('Found %d collectible appearances across slot indexes.', count($slotByAppearance)));

        $idsToFetch = $full ? array_keys($slotByAppearance) : $this->missingAppearanceIds($slotByAppearance);
        if ($limit !== null) {
            $idsToFetch = array_slice($idsToFetch, 0, $limit);
        }

        $this->info(sprintf('Fetching %d appearance details (%s mode)...', count($idsToFetch), $full ? 'full' : 'incremental'));

        $details = $this->fetchDetailsWithBudget($idsToFetch);

        [$itemIds, $mediaIds] = $this->collectReferencedIds($details);
        $this->info(sprintf('Searching item data (%d referenced items)...', count($itemIds)));
        $itemData = $this->searchByIdWindows('data/wow/search/item', $itemIds, $this->parseItemResult(...));

        // Les media d'items sont aussi cherchés : ~1/3 des media ids d'apparences
        // n'existent pas dans l'API, l'icône de l'item représentatif sert de secours.
        $mediaSearchIds = array_values(array_unique(array_merge($mediaIds, $itemIds)));
        $this->info(sprintf('Resolving icons (%d media ids)...', count($mediaSearchIds)));
        $mediaData = $this->searchByIdWindows('data/wow/search/media', $mediaSearchIds, $this->parseMediaResult(...), '&tags=item');
        $this->info(sprintf('Resolved %d item entries and %d icons.', count($itemData), count($mediaData)));

        $rows = $this->buildRows($details, $slotByAppearance, $itemData, $mediaData);
        $this->saveRows($rows);
        $this->deactivateStaleRows($slotByAppearance);
    }

    /**
     * Index par slot → [appearanceId => slot (vocabulaire base)].
     *
     * @return array<int, string>
     */
    private function fetchSlotIndexes(): array
    {
        $map = [];

        foreach (self::API_SLOTS as $apiSlot) {
            $index = $this->fetchWithRetry('data/wow/item-appearance/slot/'.$apiSlot);
            if ($index === null) {
                continue;
            }

            $slot = self::SLOT_ALIASES[$apiSlot] ?? $apiSlot;

            /** @var list<array{id?: int}> $appearances */
            $appearances = $index['appearances'] ?? [];
            foreach ($appearances as $appearance) {
                $id = (int) ($appearance['id'] ?? 0);
                if ($id > 0 && ! isset($map[$id])) {
                    $map[$id] = $slot;
                }
            }
        }

        return $map;
    }

    /**
     * IDs absents de la base ou incomplets (jamais détaillés avec succès).
     *
     * @param  array<int, string>  $slotByAppearance
     * @return list<int>
     */
    private function missingAppearanceIds(array $slotByAppearance): array
    {
        /** @var list<int> $complete */
        $complete = WowAppearance::query()
            ->whereNotNull('item_id')
            ->whereNotNull('icon_url')
            ->pluck('id')
            ->all();

        return array_values(array_diff(array_keys($slotByAppearance), $complete));
    }

    /**
     * Détails d'apparences par lots, en respectant le budget horaire d'appels API.
     * Chaque réponse JSON est immédiatement réduite à une structure compacte
     * (catégorie, media id, items) pour tenir en mémoire sur ~50 000 apparences.
     *
     * @param  list<int>  $ids
     * @return array<int, array{category: string|null, media_id: int, items: list<array{0: int, 1: string}>}>
     */
    private function fetchDetailsWithBudget(array $ids): array
    {
        $details = [];
        $fetched = 0;
        $total = count($ids);

        foreach (array_chunk($ids, self::DETAIL_CHUNK) as $chunk) {
            $wait = $this->hourlyBudgetGuard->secondsUntilAvailable(count($chunk));
            if ($wait > 0) {
                $this->info(sprintf('  Hourly API budget reached, pausing %ds...', $wait));
                Sleep::sleep($wait);
            }

            $endpoints = [];
            foreach ($chunk as $id) {
                $endpoints[$id] = 'data/wow/item-appearance/'.$id;
            }

            $results = $this->fetchBatchAsync($endpoints);
            $this->hourlyBudgetGuard->consume(count($chunk));

            foreach ($results as $id => $result) {
                if ($result !== null) {
                    $details[(int) $id] = $this->extractDetail($result);
                }
            }

            $fetched += count($chunk);
            $this->info(sprintf('  Details progress: %d/%d.', $fetched, $total));
        }

        return $details;
    }

    /**
     * Réduit une réponse de détail d'apparence aux seuls champs exploités.
     *
     * @param  array<string, mixed>  $detail
     * @return array{category: string|null, media_id: int, items: list<array{0: int, 1: string}>}
     */
    private function extractDetail(array $detail): array
    {
        /** @var array{name?: string} $itemClass */
        $itemClass = $detail['item_class'] ?? [];

        /** @var array{id?: int} $media */
        $media = $detail['media'] ?? [];

        $items = [];

        /** @var list<array{id?: int, name?: string}> $rawItems */
        $rawItems = $detail['items'] ?? [];
        foreach ($rawItems as $rawItem) {
            $itemId = (int) ($rawItem['id'] ?? 0);
            if ($itemId > 0) {
                $items[] = [$itemId, trim($rawItem['name'] ?? '')];
            }
        }

        return [
            'category' => ($itemClass['name'] ?? '') !== '' ? $itemClass['name'] : null,
            'media_id' => (int) ($media['id'] ?? 0),
            'items' => $items,
        ];
    }

    /**
     * @param  array<int, array{category: string|null, media_id: int, items: list<array{0: int, 1: string}>}>  $details
     * @return array{0: list<int>, 1: list<int>}
     */
    private function collectReferencedIds(array $details): array
    {
        $itemIds = [];
        $mediaIds = [];

        foreach ($details as $detail) {
            foreach ($detail['items'] as $item) {
                $itemIds[$item[0]] = true;
            }

            if ($detail['media_id'] > 0) {
                $mediaIds[$detail['media_id']] = true;
            }
        }

        return [array_keys($itemIds), array_keys($mediaIds)];
    }

    /**
     * Recherches bulk par fenêtres d'IDs de largeur ≤ SEARCH_WINDOW (1 000 IDs max
     * par fenêtre ⇒ une seule page par appel, garanti par l'unicité des IDs).
     *
     * @template T
     *
     * @param  list<int>  $ids
     * @param  callable(array<string, mixed>): (array{0: int, 1: T}|null)  $parseResult
     * @return array<int, T>
     */
    private function searchByIdWindows(string $endpoint, array $ids, callable $parseResult, string $extraQuery = ''): array
    {
        if ($ids === []) {
            return [];
        }

        sort($ids);

        $endpoints = [];
        $windowStart = null;
        foreach ($ids as $id) {
            if ($windowStart === null || $id >= $windowStart + self::SEARCH_WINDOW) {
                $windowStart = $id;
                $endpoints[] = sprintf(
                    '%s?_pageSize=%d&orderby=id&id=[%d,%d]%s',
                    $endpoint,
                    self::SEARCH_WINDOW,
                    $windowStart,
                    $windowStart + self::SEARCH_WINDOW - 1,
                    $extraQuery,
                );
            }
        }

        // Petits lots parsés immédiatement : chaque fenêtre peut renvoyer jusqu'à
        // 1 000 documents complets (~plusieurs Ko chacun), accumuler toutes les
        // réponses brutes ferait exploser la mémoire.
        $map = [];
        foreach (array_chunk($endpoints, self::SEARCH_BATCH) as $chunk) {
            $wait = $this->hourlyBudgetGuard->secondsUntilAvailable(count($chunk));
            if ($wait > 0) {
                $this->info(sprintf('  Hourly API budget reached, pausing %ds...', $wait));
                Sleep::sleep($wait);
            }

            $responses = $this->fetchBatchAsync($chunk);
            $this->hourlyBudgetGuard->consume(count($chunk));

            foreach ($responses as $response) {
                if ($response === null) {
                    continue;
                }

                /** @var list<array{data?: array<string, mixed>}> $results */
                $results = $response['results'] ?? [];
                foreach ($results as $result) {
                    $parsed = $parseResult($result['data'] ?? []);
                    if ($parsed !== null) {
                        $map[$parsed[0]] = $parsed[1];
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array{quality: int, name: string}}|null
     */
    private function parseItemResult(array $data): ?array
    {
        $id = is_numeric($data['id'] ?? null) ? (int) $data['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        /** @var array{type?: string} $quality */
        $quality = $data['quality'] ?? [];

        /** @var array{fr_FR?: string} $name */
        $name = $data['name'] ?? [];

        return [$id, [
            'quality' => self::QUALITY_RANKS[$quality['type'] ?? ''] ?? 1,
            'name' => trim($name['fr_FR'] ?? ''),
        ]];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array{icon_url: string, file_data_id: int|null}}|null
     */
    private function parseMediaResult(array $data): ?array
    {
        $id = is_numeric($data['id'] ?? null) ? (int) $data['id'] : 0;
        if ($id <= 0) {
            return null;
        }

        /** @var list<array{key?: string, value?: string, file_data_id?: int}> $assets */
        $assets = $data['assets'] ?? [];
        foreach ($assets as $asset) {
            if (($asset['key'] ?? '') === 'icon' && ($asset['value'] ?? '') !== '') {
                return [$id, [
                    'icon_url' => $asset['value'],
                    'file_data_id' => ($asset['file_data_id'] ?? 0) > 0 ? (int) $asset['file_data_id'] : null,
                ]];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{category: string|null, media_id: int, items: list<array{0: int, 1: string}>}>  $details
     * @param  array<int, string>  $slotByAppearance
     * @param  array<int, array{quality: int, name: string}>  $itemData
     * @param  array<int, array{icon_url: string, file_data_id: int|null}>  $mediaData
     * @return list<array{id: int, name_fr: string, slot: string|null, category: string|null, quality: int|null, item_id: int|null, icon_file_data_id: int|null, icon_url: string|null, expansion_id: int|null, source: string|null, is_active: bool}>
     */
    private function buildRows(array $details, array $slotByAppearance, array $itemData, array $mediaData): array
    {
        $rows = [];

        foreach ($details as $appearanceId => $detail) {
            $representative = $this->pickRepresentative($detail['items'], $itemData);
            $mediaInfo = $mediaData[$detail['media_id']]
                ?? ($representative['item_id'] !== null ? ($mediaData[$representative['item_id']] ?? null) : null);

            $rows[] = [
                'id' => $appearanceId,
                'name_fr' => $representative['name'] ?? sprintf('[EN] Appearance #%d', $appearanceId),
                'slot' => $slotByAppearance[$appearanceId] ?? null,
                'category' => $detail['category'],
                'quality' => $representative['quality'],
                'item_id' => $representative['item_id'],
                'icon_file_data_id' => $mediaInfo['file_data_id'] ?? null,
                'icon_url' => $mediaInfo['icon_url'] ?? null,
                'expansion_id' => null,
                'source' => null,
                'is_active' => true,
            ];
        }

        $this->info(sprintf('Built %d appearance rows.', count($rows)));

        return $rows;
    }

    /**
     * Item représentatif = meilleure qualité parmi les items liés à l'apparence.
     *
     * @param  list<array{0: int, 1: string}>  $items  [itemId, nom du détail d'apparence]
     * @param  array<int, array{quality: int, name: string}>  $itemData
     * @return array{name: string|null, quality: int|null, item_id: int|null}
     */
    private function pickRepresentative(array $items, array $itemData): array
    {
        $best = null;

        foreach ($items as [$itemId, $detailName]) {
            $quality = $itemData[$itemId]['quality'] ?? 1;
            $name = $itemData[$itemId]['name'] ?? '';
            if ($name === '') {
                $name = $detailName;
            }

            if ($name === '') {
                continue;
            }

            if ($best === null || $quality > $best['quality']) {
                $best = ['name' => $name, 'quality' => $quality, 'item_id' => $itemId];
            }
        }

        return $best ?? ['name' => null, 'quality' => null, 'item_id' => null];
    }

    /**
     * @param  list<array{id: int, name_fr: string, slot: string|null, category: string|null, quality: int|null, item_id: int|null, icon_file_data_id: int|null, icon_url: string|null, expansion_id: int|null, source: string|null, is_active: bool}>  $rows
     */
    private function saveRows(array $rows): void
    {
        $this->info(sprintf('Saving %d appearances...', count($rows)));

        $count = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            WowAppearance::query()->upsert(
                $chunk,
                uniqueBy: ['id'],
                update: ['name_fr', 'slot', 'category', 'quality', 'item_id', 'icon_file_data_id', 'icon_url', 'expansion_id', 'source', 'is_active'],
            );
            $count += count($chunk);
        }

        $this->info(sprintf('Appearance import complete: %d items.', $count));
    }

    /**
     * Désactive les apparences en base qui ne figurent plus dans les index de slots
     * (anciennes données CSV ou entrées retirées par Blizzard).
     *
     * @param  array<int, string>  $slotByAppearance
     */
    private function deactivateStaleRows(array $slotByAppearance): void
    {
        /** @var list<int> $activeIds */
        $activeIds = WowAppearance::query()->where('is_active', true)->pluck('id')->all();

        $staleIds = array_diff($activeIds, array_keys($slotByAppearance));
        if ($staleIds === []) {
            return;
        }

        foreach (array_chunk($staleIds, 500) as $chunk) {
            WowAppearance::query()->whereIn('id', $chunk)->update(['is_active' => false]);
        }

        $this->info(sprintf('Deactivated %d stale appearances.', count($staleIds)));
    }
}
