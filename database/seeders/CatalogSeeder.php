<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Полностью перезаписывает каталог данными из database/data/catalog.php.
 * На PostgreSQL сбрасывает счётчики id, поэтому id товаров всегда одинаковые.
 */
class CatalogSeeder extends Seeder
{
    public const INITIAL_STOCK = 100;

    public function run(): void
    {
        /** @var array{chapters: array<string, array{title: string, icon: string}>, categories: array<string, array<string, mixed>>} $data */
        $data = require database_path('data/catalog.php');

        $total = DB::transaction(function () use ($data): int {
            $this->wipe();
            $now = now();

            $chapterIds = [];
            $sort = 0;
            foreach ($data['chapters'] as $slug => $chapter) {
                $chapterIds[$slug] = DB::table('chapters')->insertGetId([
                    'slug' => $slug,
                    'title' => $chapter['title'],
                    'icon' => $chapter['icon'],
                    'sort' => $sort++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $rows = [];
            $sort = 0;
            foreach ($data['categories'] as $slug => $category) {
                $categoryId = DB::table('categories')->insertGetId([
                    'chapter_id' => $chapterIds[$category['chapter']],
                    'slug' => $slug,
                    'title' => $category['title'],
                    'icon' => $category['icon'],
                    'description' => $category['description'],
                    'sort' => $sort++,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $n = 0;
                foreach ($category['products'] as $item) {
                    [$name, $tags, $prices] = $item;
                    $variants = is_array($prices) ? $prices : ['' => $prices];

                    foreach ($variants as $label => $price) {
                        $sku = sprintf('SU-%s-%03d', $category['sku'], ++$n);
                        $label = (string) $label;

                        $rows[] = [
                            'category_id' => $categoryId,
                            'sku' => $sku,
                            'name' => $label === '' ? $name : $name.', '.$label,
                            'price' => $price,
                            'stock' => self::INITIAL_STOCK,
                            'icon' => $item['icon'] ?? $category['icon'],
                            'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('products')->insert($chunk);
            }

            return count($rows);
        });

        $this->command->info("Каталог загружен: {$total} товаров.");
    }

    private function wipe(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('TRUNCATE products, categories, chapters RESTART IDENTITY CASCADE');

            return;
        }

        DB::table('products')->delete();
        DB::table('categories')->delete();
        DB::table('chapters')->delete();
    }
}
