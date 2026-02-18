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
    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $redirectUri;

    private readonly string $region;

    public function __construct()
    {
        /** @var string $clientId */
        $clientId = config('services.blizzard.client_id', '');
        $this->clientId = $clientId;

        /** @var string $clientSecret */
        $clientSecret = config('services.blizzard.client_secret', '');
        $this->clientSecret = $clientSecret;

        /** @var string $redirectUri */
        $redirectUri = config('services.blizzard.redirect_uri', '');
        $this->redirectUri = $redirectUri;

        /** @var string $region */
        $region = config('services.blizzard.region', 'eu');
        $this->region = $region;
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

        return redirect(sprintf('https://%s.battle.net/oauth/authorize?%s', $this->region, $query));
    }

    public function callback(Request $request): RedirectResponse
    {
        /** @var string $code */
        $code = $request->get('code', '');
        /** @var string $state */
        $state = $request->get('state', '');
        /** @var string $expectedState */
        $expectedState = Session::pull('blizzard_oauth_state', '');

        if ($code === '' || $state === '' || $state !== $expectedState) {
            return redirect('/')->with('error', 'Authorization failed');
        }

        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post(sprintf('https://%s.battle.net/oauth/token', $this->region), [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]);

        if ($response->failed()) {
            return redirect('/')->with('error', 'Token exchange failed');
        }

        /** @var array{access_token: string} $tokenData */
        $tokenData = $response->json();
        Session::put('blizzard_user_token', $tokenData['access_token']);

        return redirect('/');
    }
}
