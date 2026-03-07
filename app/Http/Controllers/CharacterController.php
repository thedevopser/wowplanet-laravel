<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CharacterController extends Controller
{
    public function __construct(
        private readonly CharacterProfileService $characterProfileService,
    ) {}

    public function show(string $realm, string $name): JsonResponse
    {
        try {
            $profile = $this->characterProfileService->getProfile($realm, $name);

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
