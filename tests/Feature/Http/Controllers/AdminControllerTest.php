<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

test('admin page renders AdminPage via inertia for an administrator', function (): void {
    $this->withSession(['is_admin' => true])
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $assert): Assert => $assert->component('AdminPage'));
});

test('admin page redirects a non-admin visitor to home', function (): void {
    $this->get('/admin')->assertRedirect('/');
});

test('admin page redirects an authenticated non-admin to home', function (): void {
    $this->withSession(['blizzard_user_token' => 'fake-token'])
        ->get('/admin')
        ->assertRedirect('/');
});
