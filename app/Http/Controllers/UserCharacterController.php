<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\UserCharacterService;
use Illuminate\Http\JsonResponse;

class UserCharacterController extends Controller
{
    public function __construct(
        private UserCharacterService $service,
    ) {}

    public function index(): JsonResponse
    {
        if (!$this->service->isAuthenticated()) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $characters = $this->service->getUserCharacters();
            return response()->json($characters);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch characters',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function authStatus(): JsonResponse
    {
        return response()->json([
            'authenticated' => $this->service->isAuthenticated(),
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->service->logout();

        return response()->json(['success' => true]);
    }
}
