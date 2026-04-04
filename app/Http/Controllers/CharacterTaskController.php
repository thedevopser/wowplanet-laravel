<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\CharacterTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CharacterTaskController extends Controller
{
    public function __construct(
        private readonly CharacterTaskService $characterTaskService,
    ) {}

    public function index(): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $tasks = $this->characterTaskService->getTasksForUser($bnetUserId);

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validate([
            'realm_slug' => ['required', 'string'],
            'character_name' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'reset_type' => ['required', 'in:daily,weekly,monthly'],
        ]);

        $characterTask = $this->characterTaskService->createTask($bnetUserId, $validated);

        return response()->json($characterTask->refresh(), 201);
    }

    public function update(int $id): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $task = $this->characterTaskService->toggleTask($id, $bnetUserId);

            return response()->json($task);
        } catch (AccessDeniedHttpException) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $bnetUserId = $this->getAuthenticatedUserId();
        if ($bnetUserId === null) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $this->characterTaskService->deleteTask($id, $bnetUserId);

            return response()->json(null, 204);
        } catch (AccessDeniedHttpException) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
    }

    private function getAuthenticatedUserId(): ?string
    {
        if (! Session::has('blizzard_user_token')) {
            return null;
        }

        /** @var string|null */
        return Session::get('bnet_user_id');
    }
}
