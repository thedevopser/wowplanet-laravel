<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterController extends Controller
{
    public function __construct(
        private CharacterProfileService $service,
        )
    {
    }

    public function show(string $realm, string $name): JsonResponse
    {
        try {
            $profile = $this->service->getProfile($realm, $name);
            return response()->json($profile);
        }
        catch (\Exception $e) {
            return response()->json([
                'error' => 'Character not found or Blizzard API error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}