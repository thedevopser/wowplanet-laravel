<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterController extends Controller
{
    public function __construct(
        private readonly CharacterProfileService $characterProfileService,
    ) {
    }

    public function show(string $realm, string $name): JsonResponse
    {
        try {
            $profile = $this->characterProfileService->getProfile($realm, $name);
            return response()->json($profile);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => 'Character not found or Blizzard API error',
                'message' => $exception->getMessage(),
            ], 404);
        }
    }
}
