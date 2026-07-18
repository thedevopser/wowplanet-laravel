<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

dataset('account pages', [
    'my characters' => ['/my-characters', 'MyCharactersPage'],
    'class stats' => ['/class-stats', 'ClassStatsPage'],
    'my score' => ['/my-score', 'AccountScorePage'],
]);

test('renders the inertia page when authenticated', function (string $url, string $component): void {
    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component($component));
})->with('account pages');

test('redirects to home with auth marker when not authenticated', function (string $url): void {
    $this->get($url)
        ->assertRedirect('/?auth=required');
})->with('account pages');
