<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\ExpansionId;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExpansionIdTest extends TestCase
{
    #[Test]
    #[DataProvider('validExpansionsProvider')]
    public function itCreatesValidExpansionIds(int $value, string $expectedName): void
    {
        $expansionId = new ExpansionId($value);

        $this->assertSame($value, $expansionId->value);
        $this->assertSame($expectedName, $expansionId->toString());
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function validExpansionsProvider(): array
    {
        return [
            'Classic' => [0, 'Classic'],
            'Burning Crusade' => [1, 'The Burning Crusade'],
            'Wrath of the Lich King' => [2, 'Wrath of the Lich King'],
            'Cataclysm' => [3, 'Cataclysm'],
            'Mists of Pandaria' => [4, 'Mists of Pandaria'],
            'Warlords of Draenor' => [5, 'Warlords of Draenor'],
            'Legion' => [6, 'Legion'],
            'Battle for Azeroth' => [7, 'Battle for Azeroth'],
            'Shadowlands' => [8, 'Shadowlands'],
            'Dragonflight' => [9, 'Dragonflight'],
            'The War Within' => [10, 'The War Within'],
            'Midnight' => [11, 'Midnight'],
        ];
    }

    #[Test]
    #[DataProvider('invalidExpansionsProvider')]
    public function itRejectsInvalidValues(int $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Expansion ID: ' . $value);

        new ExpansionId($value);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function invalidExpansionsProvider(): array
    {
        return [
            'negative' => [-1],
            'too high' => [12],
            'way too high' => [999],
        ];
    }

    #[Test]
    public function itUsesCorrectConstants(): void
    {
        $this->assertSame(0, ExpansionId::CLASSIC);
        $this->assertSame(1, ExpansionId::BURNING_CRUSADE);
        $this->assertSame(10, ExpansionId::THE_WAR_WITHIN);
        $this->assertSame(11, ExpansionId::MIDNIGHT);
    }
}
