<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\Progress\TalentAggregator;
use App\Infrastructure\Blizzard\BlizzardApiClient;
use GuzzleHttp\Promise\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TalentController extends Controller
{
    public function __construct(
        private readonly BlizzardApiClient $blizzardApiClient,
        private readonly TalentAggregator $talentAggregator,
    ) {}

    public function show(string $realm, string $name): JsonResponse
    {
        $realm = mb_strtolower($realm);
        $name = mb_strtolower($name);

        try {
            $base = sprintf('profile/wow/character/%s/%s', $realm, $name);

            /** @var array<string, mixed> $specResponse */
            $specResponse = $this->blizzardApiClient->get($base.'/specializations');

            /** @var array{id?: int} $activeSpec */
            $activeSpec = $specResponse['active_specialization'] ?? [];
            $specId = (int) ($activeSpec['id'] ?? 0);

            if ($specId === 0) {
                return response()->json(['error' => 'No active specialization'], 404);
            }

            $talentTreeId = $this->resolveTalentTreeId($specId);

            if ($talentTreeId === 0) {
                return response()->json(['error' => 'Talent tree not found'], 404);
            }

            $talentTreeResponse = $this->fetchTalentTree($talentTreeId, $specId);
            $result = $this->talentAggregator->aggregate($specResponse, $talentTreeResponse);

            return response()->json($result);
        } catch (\Exception $exception) {
            Log::error('Failed to fetch talents', [
                'realm' => $realm,
                'name' => $name,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to fetch talent data'], 500);
        }
    }

    private function resolveTalentTreeId(int $specId): int
    {
        /** @var int $treeId */
        $treeId = Cache::remember(
            sprintf('playable_spec:%d', $specId),
            604800, // 7 days
            function () use ($specId): int {
                $region = $this->blizzardApiClient->getRegion();

                /** @var array<string, mixed> $response */
                $response = $this->blizzardApiClient->get(
                    sprintf('data/wow/playable-specialization/%d', $specId),
                    ['namespace' => 'static-'.$region],
                );

                /** @var array{key?: array{href?: string}} $talentTree */
                $talentTree = $response['spec_talent_tree'] ?? [];
                $href = (string) ($talentTree['key']['href'] ?? '');

                if (preg_match('/talent-tree\/(\d+)/', $href, $matches)) {
                    return (int) $matches[1];
                }

                return 0;
            },
        );

        return (int) $treeId;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTalentTree(int $talentTreeId, int $specId): array
    {
        $cacheKey = sprintf('talent_tree:%d:%d', $talentTreeId, $specId);
        $region = $this->blizzardApiClient->getRegion();

        /** @var array<string, mixed> $response */
        $response = Cache::remember(
            $cacheKey,
            604800, // 7 days
            function () use ($talentTreeId, $specId, $region): array {
                /** @var array<string, mixed> $tree */
                $tree = $this->blizzardApiClient->get(
                    sprintf('data/wow/talent-tree/%d/playable-specialization/%d', $talentTreeId, $specId),
                    ['namespace' => 'static-'.$region],
                );

                $iconMap = $this->fetchSpellIcons($this->collectSpellIds($tree), $region);

                return $this->injectIcons($tree, $iconMap);
            },
        );

        return $response;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<int>
     */
    private function collectSpellIds(array $tree): array
    {
        $ids = [];

        /** @var list<array<string, mixed>> $classNodes */
        $classNodes = $tree['class_talent_nodes'] ?? [];
        $this->collectSpellIdsFromNodes($classNodes, $ids);

        /** @var list<array<string, mixed>> $specNodes */
        $specNodes = $tree['spec_talent_nodes'] ?? [];
        $this->collectSpellIdsFromNodes($specNodes, $ids);

        /** @var list<array<string, mixed>> $heroTrees */
        $heroTrees = $tree['hero_talent_trees'] ?? [];
        foreach ($heroTrees as $heroTree) {
            /** @var list<array<string, mixed>> $heroNodes */
            $heroNodes = $heroTree['hero_talent_nodes'] ?? [];
            $this->collectSpellIdsFromNodes($heroNodes, $ids);
        }

        return array_keys($ids);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<int, true>  $ids
     */
    private function collectSpellIdsFromNodes(array $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            /** @var list<array<string, mixed>> $ranks */
            $ranks = $node['ranks'] ?? [];

            foreach ($ranks as $rank) {
                /** @var array<string, mixed> $tooltip */
                $tooltip = $rank['tooltip'] ?? [];
                /** @var array<string, mixed> $spellTooltip */
                $spellTooltip = $tooltip['spell_tooltip'] ?? [];
                /** @var array<string, mixed> $spell */
                $spell = $spellTooltip['spell'] ?? [];
                $spellId = $spell['id'] ?? null;
                if (is_int($spellId)) {
                    $ids[$spellId] = true;
                }

                /** @var list<array<string, mixed>> $choices */
                $choices = $rank['choice_of_tooltips'] ?? [];
                foreach ($choices as $choice) {
                    /** @var array<string, mixed> $choiceSpellTooltip */
                    $choiceSpellTooltip = $choice['spell_tooltip'] ?? [];
                    /** @var array<string, mixed> $choiceSpell */
                    $choiceSpell = $choiceSpellTooltip['spell'] ?? [];
                    $choiceSpellId = $choiceSpell['id'] ?? null;
                    if (is_int($choiceSpellId)) {
                        $ids[$choiceSpellId] = true;
                    }
                }
            }
        }
    }

    /**
     * @param  list<int>  $spellIds
     * @return array<int, string>
     */
    private function fetchSpellIcons(array $spellIds, string $region): array
    {
        if ($spellIds === []) {
            return [];
        }

        $iconMap = [];

        // Batch spell icons into chunks to avoid overwhelming the API.
        foreach (array_chunk($spellIds, 50) as $chunk) {
            $promises = [];
            foreach ($chunk as $spellId) {
                $cached = Cache::get(sprintf('spell_icon:%d', $spellId));
                if (is_string($cached)) {
                    $iconMap[$spellId] = $cached;

                    continue;
                }

                $promises[$spellId] = $this->blizzardApiClient->getAsync(
                    sprintf('data/wow/media/spell/%d', $spellId),
                    ['namespace' => 'static-'.$region],
                );
            }

            if ($promises === []) {
                continue;
            }

            /** @var array<int, array{state: string, value?: \Psr\Http\Message\ResponseInterface}> $settled */
            $settled = Utils::settle($promises)->wait();

            foreach ($settled as $spellId => $result) {
                if ($result['state'] !== 'fulfilled') {
                    continue;
                }

                if (! isset($result['value'])) {
                    continue;
                }

                try {
                    /** @var array<string, mixed> $body */
                    $body = json_decode($result['value']->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                    $result['value']->getBody()->close();

                    /** @var list<array{key?: string, value?: string}> $assets */
                    $assets = $body['assets'] ?? [];
                    foreach ($assets as $asset) {
                        if (($asset['key'] ?? '') === 'icon' && isset($asset['value'])) {
                            $iconMap[$spellId] = $asset['value'];
                            Cache::put(sprintf('spell_icon:%d', $spellId), $asset['value'], 2592000); // 30 days
                            break;
                        }
                    }
                } catch (\Throwable $throwable) {
                    Log::debug('Spell icon fetch failed', ['spell_id' => $spellId, 'exception' => $throwable->getMessage()]);
                }
            }
        }

        return $iconMap;
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<int, string>  $iconMap
     * @return array<string, mixed>
     */
    private function injectIcons(array $tree, array $iconMap): array
    {
        /** @var list<array<string, mixed>> $classNodes */
        $classNodes = $tree['class_talent_nodes'] ?? [];
        $tree['class_talent_nodes'] = $this->injectIconsIntoNodes($classNodes, $iconMap);

        /** @var list<array<string, mixed>> $specNodes */
        $specNodes = $tree['spec_talent_nodes'] ?? [];
        $tree['spec_talent_nodes'] = $this->injectIconsIntoNodes($specNodes, $iconMap);

        /** @var list<array<string, mixed>> $heroTrees */
        $heroTrees = $tree['hero_talent_trees'] ?? [];
        $newHeroTrees = [];
        foreach ($heroTrees as $heroTree) {
            /** @var list<array<string, mixed>> $heroNodes */
            $heroNodes = $heroTree['hero_talent_nodes'] ?? [];
            $heroTree['hero_talent_nodes'] = $this->injectIconsIntoNodes($heroNodes, $iconMap);
            $newHeroTrees[] = $heroTree;
        }

        $tree['hero_talent_trees'] = $newHeroTrees;

        return $tree;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<int, string>  $iconMap
     * @return list<array<string, mixed>>
     */
    private function injectIconsIntoNodes(array $nodes, array $iconMap): array
    {
        $result = [];

        foreach ($nodes as $node) {
            /** @var list<array<string, mixed>> $ranks */
            $ranks = $node['ranks'] ?? [];
            $newRanks = [];

            foreach ($ranks as $rank) {
                /** @var array<string, mixed> $tooltip */
                $tooltip = $rank['tooltip'] ?? [];
                /** @var array<string, mixed> $spellTooltip */
                $spellTooltip = $tooltip['spell_tooltip'] ?? [];
                /** @var array<string, mixed> $spell */
                $spell = $spellTooltip['spell'] ?? [];
                $spellId = $spell['id'] ?? null;
                if (is_int($spellId) && isset($iconMap[$spellId])) {
                    $tooltip['icon_url'] = $iconMap[$spellId];
                    $rank['tooltip'] = $tooltip;
                }

                /** @var list<array<string, mixed>> $choices */
                $choices = $rank['choice_of_tooltips'] ?? [];
                if ($choices !== []) {
                    $newChoices = [];
                    foreach ($choices as $choice) {
                        /** @var array<string, mixed> $choiceSpellTooltip */
                        $choiceSpellTooltip = $choice['spell_tooltip'] ?? [];
                        /** @var array<string, mixed> $choiceSpell */
                        $choiceSpell = $choiceSpellTooltip['spell'] ?? [];
                        $choiceSpellId = $choiceSpell['id'] ?? null;
                        if (is_int($choiceSpellId) && isset($iconMap[$choiceSpellId])) {
                            $choice['icon_url'] = $iconMap[$choiceSpellId];
                        }

                        $newChoices[] = $choice;
                    }

                    $rank['choice_of_tooltips'] = $newChoices;
                }

                $newRanks[] = $rank;
            }

            $node['ranks'] = $newRanks;
            $result[] = $node;
        }

        return $result;
    }
}
