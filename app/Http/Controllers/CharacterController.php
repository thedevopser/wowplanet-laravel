<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterProfileService;
use App\Application\Services\CrossCharacterService;
use App\Application\Services\UserCharacterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CharacterController extends Controller
{
    public function __construct(
        private readonly CharacterProfileService $characterProfileService,
        private readonly CrossCharacterService $crossCharacterService,
        private readonly UserCharacterService $userCharacterService,
    ) {}

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
