<?php

declare(strict_types=1);

namespace App\Infrastructure\Parsers;

class MountCategoryMapper
{
    /**
     * Build a mapping of mount ID → {category, source} from the SimpleArmory JSON.
     *
     * @return array<int, array{category: string, source: string}>
     */
    public static function build(): array
    {
        $path = storage_path('app/blizzard/mounts_categories.json');
        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return [];
        }

        /** @var list<array<string, mixed>>|null $data */
        $data = json_decode($content, true);
        if (! is_array($data)) {
            return [];
        }

        $map = [];

        foreach ($data as $category) {
            /** @var string $categoryName */
            $categoryName = $category['name'] ?? '';
            if ($categoryName === '') {
                continue;
            }

            /** @var list<array<string, mixed>> $subcats */
            $subcats = $category['subcats'] ?? [];

            foreach ($subcats as $subcat) {
                /** @var string $sourceName */
                $sourceName = $subcat['name'] ?? '';
                if ($sourceName === '') {
                    continue;
                }

                /** @var list<array<string, mixed>> $items */
                $items = $subcat['items'] ?? [];

                foreach ($items as $item) {
                    if (! isset($item['ID'])) {
                        continue;
                    }

                    if (! is_int($item['ID'])) {
                        continue;
                    }

                    $map[$item['ID']] = [
                        'category' => $categoryName,
                        'source' => $sourceName,
                    ];
                }
            }
        }

        return $map;
    }
}
