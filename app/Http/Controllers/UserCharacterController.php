<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\UserCharacterService;
use Illuminate\Http\JsonResponse;

class UserCharacterController extends Controller
{
    public function __construct(
        private readonly UserCharacterService $userCharacterService,
    ) {
    }

    public function index(): JsonResponse
    {
        if (!$this->userCharacterService->isAuthenticated()) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $characters = $this->userCharacterService->getUserCharacters();
            return response()->json($characters);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => 'Failed to fetch characters',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function authStatus(): JsonResponse
    {
        return response()->json([
            'authenticated' => $this->userCharacterService->isAuthenticated(),
        ]);
    }

    public function classIcons(): JsonResponse
    {
        try {
            return response()->json($this->userCharacterService->getClassIcons());
        } catch (\Exception) {
            return response()->json([], 500);
        }
    }

    public function logout(): JsonResponse
    {
        $this->userCharacterService->logout();

        return response()->json(['success' => true]);
    }
}
