<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterProfileService;
use App\Application\Services\CharacterSeoService;
use App\Application\Services\CrossCharacterService;
use App\Application\Services\UserCharacterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class CharacterController extends Controller
{
    public function __construct(
        private readonly CharacterProfileService $characterProfileService,
        private readonly CrossCharacterService $crossCharacterService,
        private readonly UserCharacterService $userCharacterService,
        private readonly CharacterSeoService $characterSeoService,
    ) {}

    /**
     * Page personnage rendue via Inertia (SSR-compatible).
     */
    public function page(Request $request, string $realm, string $name): InertiaResponse|Response
    {
        $normalizedRealm = mb_strtolower($realm);
        $normalizedName = mb_strtolower($name);

        if ($realm !== $normalizedRealm || $name !== $normalizedName) {
            return redirect(sprintf('/character/%s/%s', $normalizedRealm, $normalizedName), 301);
        }

        try {
            $profile = $this->characterProfileService->getProfile($realm, $name);

            if ($this->userCharacterService->isAuthenticated()) {
                try {
                    $this->crossCharacterService->mergeCurrentCharacter($profile);
                } catch (\Exception $exception) {
                    Log::debug('Cross-character piggyback failed', ['exception' => $exception->getMessage()]);
                }
            }

            return Inertia::render('CharacterPage', [
                'character' => $profile,
                'realm' => $normalizedRealm,
                'name' => $normalizedName,
                'meta' => $this->characterSeoService->buildCharacterMeta($profile, $realm, $name),
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to render character page', [
                'realm' => $realm,
                'name' => $name,
                'exception' => $exception->getMessage(),
            ]);

            return Inertia::render('CharacterPage', [
                'character' => null,
                'realm' => $normalizedRealm,
                'name' => $normalizedName,
                'meta' => $this->characterSeoService->buildNotFoundCharacterMeta($realm, $name),
            ])->toResponse($request)->setStatusCode(404);
        }
    }

    public function show(string $realm, string $name): JsonResponse
    {
        try {
            $profile = $this->characterProfileService->getProfile($realm, $name);

            if ($this->userCharacterService->isAuthenticated()) {
                try {
                    $this->crossCharacterService->mergeCurrentCharacter($profile);
                } catch (\Exception $exception) {
                    Log::debug('Cross-character piggyback failed', ['exception' => $exception->getMessage()]);
                }
            }

            return response()->json($profile);
        } catch (\Exception $exception) {
            Log::error('Failed to fetch character profile', [
                'realm' => $realm,
                'name' => $name,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Character not found or Blizzard API error',
            ], 404);
        }
    }
}
