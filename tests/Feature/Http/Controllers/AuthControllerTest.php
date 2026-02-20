<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

test('redirect sends to blizzard oauth', function (): void {
    $testResponse = $this->get('/auth/blizzard/redirect');

    $testResponse->assertRedirect();

    $location = $testResponse->headers->get('Location');
    expect($location)->toBeString()
        ->toContain('battle.net/oauth/authorize')
        ->toContain('wow.profile');
});

test('callback rejects invalid state', function (): void {
    $this->get('/auth/blizzard/callback?code=abc&state=invalid')
        ->assertRedirect('/');
});

test('callback exchanges code for token', function (): void {
    Http::fake([
        '*.battle.net/oauth/token' => Http::response([
            'access_token' => 'test-token-123',
            'token_type' => 'bearer',
            'expires_in' => 86399,
        ]),
    ]);

    $state = 'test-state-value';
    $this->withSession(['blizzard_oauth_state' => $state])
        ->get('/auth/blizzard/callback?code=valid-code&state='.$state)
        ->assertRedirect('/');
});
