<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Services\UserCharacterService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserCharacterControllerTest extends TestCase
{
    #[Test]
    public function authStatusReturnsAuthenticatedFalseByDefault(): void
    {
        $mock = $this->mock(UserCharacterService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('isAuthenticated');
        $exp->once()->andReturn(false);

        $testResponse = $this->getJson('/api/auth/status');

        $testResponse->assertOk()
            ->assertJson(['authenticated' => false]);
    }

    #[Test]
    public function authStatusReturnsTrueWhenAuthenticated(): void
    {
        $mock = $this->mock(UserCharacterService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('isAuthenticated');
        $exp->once()->andReturn(true);

        $testResponse = $this->getJson('/api/auth/status');

        $testResponse->assertOk()
            ->assertJson(['authenticated' => true]);
    }

    #[Test]
    public function logoutClearsSession(): void
    {
        $mock = $this->mock(UserCharacterService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('logout');
        $exp->once();

        $testResponse = $this->postJson('/api/auth/logout');

        $testResponse->assertOk();
    }

    #[Test]
    public function indexReturns401WhenNotAuthenticated(): void
    {
        $mock = $this->mock(UserCharacterService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('isAuthenticated');
        $exp->once()->andReturn(false);

        $testResponse = $this->getJson('/api/user/characters');

        $testResponse->assertUnauthorized();
    }

    #[Test]
    public function classIconsReturnsJsonStructure(): void
    {
        $mock = $this->mock(UserCharacterService::class);
        /** @var \Mockery\Expectation $exp */
        $exp = $mock->shouldReceive('getClassIcons');
        $exp->once()->andReturn([
            1 => 'https://example.com/warrior.jpg',
            2 => 'https://example.com/paladin.jpg',
        ]);

        $testResponse = $this->getJson('/api/class-icons');

        $testResponse->assertOk()
            ->assertJsonCount(2);
    }
}
