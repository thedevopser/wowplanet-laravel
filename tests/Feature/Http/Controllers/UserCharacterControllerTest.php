<?php

declare(strict_types=1);

use App\Application\Services\UserCharacterService;

test('auth status returns authenticated false by default', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('isAuthenticated');
    $exp->once()->andReturn(false);

    $this->getJson('/api/auth/status')
        ->assertOk()
        ->assertJson(['authenticated' => false]);
});

test('auth status returns true when authenticated', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('isAuthenticated');
    $exp->once()->andReturn(true);

    $this->getJson('/api/auth/status')
        ->assertOk()
        ->assertJson(['authenticated' => true]);
});

test('logout clears session', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('logout');
    $exp->once();

    $this->postJson('/api/auth/logout')->assertOk();
});

test('index returns 401 when not authenticated', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('isAuthenticated');
    $exp->once()->andReturn(false);

    $this->getJson('/api/user/characters')->assertUnauthorized();
});

test('class icons returns json structure', function (): void {
    $mock = $this->mock(UserCharacterService::class);
    /** @var \Mockery\Expectation $exp */
    $exp = $mock->shouldReceive('getClassIcons');
    $exp->once()->andReturn([
        1 => 'https://example.com/warrior.jpg',
        2 => 'https://example.com/paladin.jpg',
    ]);

    $this->getJson('/api/class-icons')
        ->assertOk()
        ->assertJsonCount(2);
});
