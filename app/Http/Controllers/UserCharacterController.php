<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\AccountScoreService;
use App\Application\Services\UserCharacterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UserCharacterController extends Controller
{
    public function __construct(
        private readonly UserCharacterService $userCharacterService,
        private readonly AccountScoreService $accountScoreService,
    ) {}

    public function index(): JsonResponse
    {
        if (! $this->userCharacterService->isAuthenticated()) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $characters = $this->userCharacterService->getUserCharacters();

            return response()->json($characters);
        } catch (\Exception $exception) {
            Log::error('Failed to fetch characters', ['exception' => $exception->getMessage()]);

            return response()->json([
                'error' => 'Failed to fetch characters',
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

    public function accountScore(): JsonResponse
    {
        try {
            $result = $this->accountScoreService->getOrCompute();

            if ($result['status'] === 'unauthenticated') {
                return response()->json(['error' => 'Not authenticated'], 401);
            }

            return response()->json($result);
        } catch (\Exception $exception) {
            Log::error('Failed to compute account score', ['exception' => $exception->getMessage()]);

            return response()->json([
                'error' => 'Failed to compute account score',
            ], 500);
        }
    }

    public function refreshAccountScore(): JsonResponse
    {
        if (! $this->userCharacterService->isAuthenticated()) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $this->accountScoreService->invalidate();

        return response()->json(['success' => true]);
    }
}
