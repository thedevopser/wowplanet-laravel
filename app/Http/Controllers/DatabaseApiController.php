<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\DatabaseQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatabaseApiController extends Controller
{
    public function __construct(
        private readonly DatabaseQueryService $databaseQueryService,
    ) {}

    public function mounts(Request $request): JsonResponse
    {
        return response()->json($this->databaseQueryService->mounts($this->stringQuery($request, 'category')));
    }

    public function achievements(Request $request): JsonResponse
    {
        return response()->json($this->databaseQueryService->achievements(
            $this->stringQuery($request, 'expansion'),
            $this->stringQuery($request, 'search'),
            $this->intQuery($request, 'page'),
            $this->intQuery($request, 'per_page'),
        ));
    }

    public function quests(Request $request): JsonResponse
    {
        return response()->json($this->databaseQueryService->quests(
            $this->stringQuery($request, 'expansion'),
            $this->stringQuery($request, 'search'),
            $this->intQuery($request, 'page'),
            $this->intQuery($request, 'per_page'),
        ));
    }

    public function pets(Request $request): JsonResponse
    {
        return response()->json($this->databaseQueryService->pets($this->stringQuery($request, 'category')));
    }

    public function decors(Request $request): JsonResponse
    {
        return response()->json($this->databaseQueryService->decors($this->stringQuery($request, 'category')));
    }

    public function appearances(Request $request): JsonResponse
    {
        return response()->json($this->databaseQueryService->appearances(
            $this->stringQuery($request, 'slot'),
            $this->stringQuery($request, 'quality'),
            $this->stringQuery($request, 'search'),
            $this->intQuery($request, 'page'),
            $this->intQuery($request, 'per_page'),
        ));
    }

    public function professions(): JsonResponse
    {
        return response()->json($this->databaseQueryService->professions());
    }

    public function professionRecipes(Request $request): JsonResponse
    {
        return response()->json($this->databaseQueryService->professionRecipes(
            $this->stringQuery($request, 'profession'),
            $this->stringQuery($request, 'expansion'),
            $this->stringQuery($request, 'search'),
            $this->intQuery($request, 'page'),
            $this->intQuery($request, 'per_page'),
        ));
    }

    public function subcategories(string $section): JsonResponse
    {
        $items = $this->databaseQueryService->subcategories($section);

        abort_if($items === null, 404);

        return response()->json(['items' => $items]);
    }

    public function counts(): JsonResponse
    {
        return response()->json($this->databaseQueryService->counts());
    }

    private function stringQuery(Request $request, string $key): ?string
    {
        /** @var string|null $value */
        $value = $request->query($key);

        return $value;
    }

    private function intQuery(Request $request, string $key): ?int
    {
        /** @var string|null $value */
        $value = $request->query($key);

        return ($value === null || $value === '') ? null : (int) $value;
    }
}
