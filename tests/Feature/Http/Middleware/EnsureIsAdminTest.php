<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Http\Request;

test('it rejects requests without the admin session flag', function (): void {
    $request = Request::create('/api/admin/status');
    $request->setLaravelSession(resolve(\Illuminate\Contracts\Session\Session::class));

    $response = (new EnsureIsAdmin)->handle($request, fn (): \Illuminate\Http\JsonResponse => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(403);
});

test('it lets admin sessions through', function (): void {
    $request = Request::create('/api/admin/status');
    $request->setLaravelSession(resolve(\Illuminate\Contracts\Session\Session::class));
    $request->session()->put('is_admin', true);

    $response = (new EnsureIsAdmin)->handle($request, fn (): \Illuminate\Http\JsonResponse => response()->json(['ok' => true]));

    expect($response->getStatusCode())->toBe(200);
});
