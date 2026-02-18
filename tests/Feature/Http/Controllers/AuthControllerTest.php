<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    #[Test]
    public function redirectSendsToBlizzardOAuth(): void
    {
        $testResponse = $this->get('/auth/blizzard/redirect');

        $testResponse->assertRedirect();

        $location = $testResponse->headers->get('Location');
        $this->assertIsString($location);
        $this->assertStringContainsString('battle.net/oauth/authorize', $location);
        $this->assertStringContainsString('wow.profile', $location);
    }

    #[Test]
    public function callbackRejectsInvalidState(): void
    {
        $testResponse = $this->get('/auth/blizzard/callback?code=abc&state=invalid');

        $testResponse->assertRedirect('/');
    }

    #[Test]
    public function callbackExchangesCodeForToken(): void
    {
        Http::fake([
            '*.battle.net/oauth/token' => Http::response([
                'access_token' => 'test-token-123',
                'token_type' => 'bearer',
                'expires_in' => 86399,
            ]),
        ]);

        $state = 'test-state-value';
        $testResponse = $this->withSession(['blizzard_oauth_state' => $state])
            ->get('/auth/blizzard/callback?code=valid-code&state=' . $state);

        $testResponse->assertRedirect('/');
    }
}
