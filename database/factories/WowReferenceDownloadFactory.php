<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WowReferenceDownload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WowReferenceDownload>
 */
class WowReferenceDownloadFactory extends Factory
{
    protected $model = WowReferenceDownload::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $source = fake()->randomElement(['Faction', 'ContentTuning', 'AreaTable', 'CurrencyTypes']);
        $build = sprintf('12.1.0.%d', fake()->numberBetween(60000, 69999));

        return [
            'filename' => sprintf('%s-%s.csv', mb_strtolower((string) $source), $build),
            'source_table' => $source,
            'build' => $build,
            'bytes' => fake()->numberBetween(1000, 5_000_000),
            'row_count' => fake()->numberBetween(100, 20000),
            'downloaded_at' => now(),
        ];
    }
}
