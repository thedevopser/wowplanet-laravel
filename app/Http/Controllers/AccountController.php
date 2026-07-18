<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\UserCharacterService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AccountController extends Controller
{
    public function __construct(
        private readonly UserCharacterService $userCharacterService,
    ) {}

    public function myCharacters(): InertiaResponse|RedirectResponse
    {
        return $this->renderIfAuthenticated('MyCharactersPage');
    }

    public function classStats(): InertiaResponse|RedirectResponse
    {
        return $this->renderIfAuthenticated('ClassStatsPage');
    }

    public function myScore(): InertiaResponse|RedirectResponse
    {
        return $this->renderIfAuthenticated('AccountScorePage');
    }

    /**
     * Rend la page Inertia si l'utilisateur est connecté, sinon redirige vers
     * l'accueil avec un marqueur déclenchant le message « connexion requise ».
     */
    private function renderIfAuthenticated(string $component): InertiaResponse|RedirectResponse
    {
        if (! $this->userCharacterService->isAuthenticated()) {
            return redirect('/?auth=required');
        }

        return Inertia::render($component);
    }
}
