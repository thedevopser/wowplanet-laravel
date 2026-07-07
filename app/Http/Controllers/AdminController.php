<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'maintenance' => $this->adminService->isInMaintenance(),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'command' => ['required', 'string', 'in:app:download-db2,app:wow-data-import,app:wow-data-refresh,app:wow-quest-faction-tag'],
            'type' => ['nullable', 'string', 'in:all,achievements,quests,mounts,pets,professions,decor,appearances'],
        ]);

        /** @var string $command */
        $command = $request->input('command');

        /** @var array<string, mixed> $params */
        $params = [];
        if ($request->input('type') !== null) {
            $params['--type'] = $request->input('type');
        }

        if ($command === 'app:wow-data-refresh') {
            $params['--force'] = true;
        }

        $jobId = Str::uuid()->toString();
        Cache::put('admin_import:'.$jobId, ['status' => 'pending', 'output' => null], 3600);

        dispatch(new \App\Jobs\RunImportJob($jobId, $command, $params));

        return response()->json(['jobId' => $jobId]);
    }

    public function importStatus(string $jobId): JsonResponse
    {
        $status = $this->adminService->getImportJobStatus($jobId);

        return response()->json($status);
    }

    public function clearCache(): JsonResponse
    {
        $output = $this->adminService->clearCaches();

        return response()->json(['output' => $output]);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $request->validate([
            'enable' => ['required', 'boolean'],
            'secret' => ['nullable', 'string', 'min:8'],
        ]);

        /** @var string|null $secret */
        $secret = $request->input('secret');

        $this->adminService->toggleMaintenance(
            (bool) $request->input('enable'),
            $secret,
        );

        return response()->json([
            'maintenance' => $this->adminService->isInMaintenance(),
        ]);
    }

    public function discord(Request $request): JsonResponse
    {
        $request->validate([
            'channel' => ['required', 'string', 'in:changelog,discussion'],
            'title' => ['required', 'string', 'max:256'],
            'description' => ['required', 'string', 'max:4096'],
            'color' => ['nullable', 'integer'],
            'fields' => ['nullable', 'array', 'max:25'],
            'fields.*.name' => ['required_with:fields', 'string', 'max:256'],
            'fields.*.value' => ['required_with:fields', 'string', 'max:1024'],
            'fields.*.inline' => ['nullable', 'boolean'],
            'footer' => ['nullable', 'string', 'max:2048'],
        ]);

        /** @var string $title */
        $title = $request->input('title');
        /** @var string $description */
        $description = $request->input('description');
        /** @var string $channel */
        $channel = $request->input('channel');

        /** @var array{title: string, description: string, color?: int, fields?: list<array{name: string, value: string, inline?: bool}>} $embed */
        $embed = [
            'title' => $title,
            'description' => $description,
        ];

        if ($request->input('color') !== null) {
            /** @var int $color */
            $color = $request->input('color');
            $embed['color'] = $color;
        }

        /** @var list<array{name: string, value: string, inline?: bool}>|null $fields */
        $fields = $request->input('fields');
        if ($fields !== null) {
            $embed['fields'] = $fields;
        }

        /** @var string|null $footer */
        $footer = $request->input('footer');
        if ($footer !== null && $footer !== '') {
            $embed['footer'] = ['text' => $footer];
        }

        $success = $this->adminService->sendDiscordEmbed($channel, $embed);

        return response()->json(['success' => $success]);
    }
}
