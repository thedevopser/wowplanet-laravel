<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private string $region;

    public function __construct()
    {
        $this->clientId = (string)config('services.blizzard.client_id');
        $this->clientSecret = (string)config('services.blizzard.client_secret');
        $this->redirectUri = (string)config('services.blizzard.redirect_uri');
        $this->region = (string)config('services.blizzard.region', 'eu');
    }

    public function redirect(): RedirectResponse
    {
        $state = Str::random(40);
        Session::put('blizzard_oauth_state', $state);

        $query = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'wow.profile',
            'state' => $state,
        ]);

        return redirect("https://{$this->region}.battle.net/oauth/authorize?{$query}");
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = $request->get('code');
        $state = $request->get('state');
        $expectedState = Session::pull('blizzard_oauth_state');

        if (!$code || !$state || $state !== $expectedState) {
            return redirect('/')->with('error', 'Authorization failed');
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post("https://{$this->region}.battle.net/oauth/token", [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]);

        if ($response->failed()) {
            return redirect('/')->with('error', 'Token exchange failed');
        }

        $tokenData = $response->json();
        Session::put('blizzard_user_token', $tokenData['access_token']);

        return redirect('/');
    }
}