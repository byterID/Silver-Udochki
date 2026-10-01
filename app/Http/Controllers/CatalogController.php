<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Support\Carbon;

class CatalogController extends Controller
{
    /** Главная: лавка с продавцом. */
    public function home(Request $request): View
    {
        $user = $request->user();
        $promos = $this->activePromos();

        $front = [
            'greeting'     => $user ? 'А, '.$user->name.'! Рад снова видеть.' : 'Здравствуй, путник!',
            'questions'    => config('shop.questions', []),
            'idle'         => config('shop.idle_replies', []),
            'promos'       => array_column($promos, 'title'),
            'searchUrl'    => route('catalog.search'),
            'firstChapter' => array_key_first(config('shop.chapters', [])),
            'catalog'      => $this->catalogData(),
            'options'      => [
                ['type' => 'link',  'label' => 'Покажи удочки',      'url' => route('catalog.category', 'rods'), 'reply' => 'Пойдём, у меня их целая стойка!'],
                ['type' => 'link',  'label' => 'Нужна наживка',      'url' => route('catalog.category', 'bait'), 'reply' => 'Свеженькая, утром завезли. Сейчас покажу.'],
                ['type' => 'book',  'label' => 'Собираюсь на охоту', 'chapter' => 'hunting', 'reply' => 'Глянь на экран, раздел про охоту я открыл.'],
                ['type' => 'promo', 'label' => 'Что по акциям?'],
                ['type' => 'idle',  'label' => 'Просто смотрю'],
            ],
        ];

        return view('welcome', compact('front', 'promos'));
    }

    /** Только акции, которые действуют прямо сейчас. ends — метка в мс для таймера в браузере. */
    private function activePromos(): array
    {
        $now = now();

        return collect(config('shop.promos', []))
            ->filter(function (array $p) use ($now): bool {
                $starts = isset($p['starts_at']) ? Carbon::parse($p['starts_at']) : null;
                $ends   = isset($p['ends_at']) ? Carbon::parse($p['ends_at']) : null;

                return (! $starts || $starts->lte($now)) && (! $ends || $ends->gt($now));
            })
            ->map(fn (array $p) => [
                'title' => $p['title'],
                'note'  => $p['note'] ?? null,
                'ends'  => isset($p['ends_at']) ? Carbon::parse($p['ends_at'])->getTimestampMs() : null,
            ])
            ->values()
            ->all();
    }

    /** Данные для «монитора»: главы, категории и товары внутри них. */
    private function catalogData(): array
    {
        $categories = config('shop.categories', []);
        $products = collect(config('shop.products', []))->groupBy('category');

        return [
            'chapters' => collect(config('shop.chapters', []))
                ->map(fn (array $c) => [
                    'title'      => $c['title'],
                    'icon'       => $c['icon'],
                    'categories' => array_values(array_filter($c['categories'], fn (string $s) => isset($categories[$s]))),
                ])
                ->all(),
            'categories' => collect($categories)
                ->map(fn (array $c, string $slug) => [
                    'title'       => $c['title'],
                    'icon'        => $c['icon'],
                    'description' => $c['description'],
                    'url'         => route('catalog.category', $slug),
                    'products'    => $products->get($slug, collect())
                        ->map(fn (array $p) => [
                            'id'    => $p['id'],
                            'name'  => $p['name'],
                            'price' => $p['price'],
                            'icon'  => $p['icon'],
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
        ];
    }


    public function category(string $slug): View
    {
        $category = config('shop.categories.'.$slug);
        abort_if($category === null, 404);

        $products = array_values(array_filter(
            config('shop.products', []),
            fn (array $p) => $p['category'] === $slug,
        ));

        return view('catalog.category', compact('slug', 'category', 'products'));
    }

    public function search(Request $request): View
    {
        $raw = $request->query('q');
        $query = Str::limit(trim(is_string($raw) ? $raw : ''), 100, '');
        $words = $this->searchWords($query);
        $categories = config('shop.categories', []);

        $scored = [];
        foreach (config('shop.products', []) as $product) {
            $haystack = $this->normalize(implode(' ', [
                $product['name'],
                $categories[$product['category']]['title'] ?? '',
                implode(' ', $product['tags'] ?? []),
            ]));

            $score = count(array_filter($words, fn (string $w) => str_contains($haystack, $w)));

            if ($score > 0) {
                $scored[] = ['score' => $score, 'product' => $product];
            }
        }

        usort($scored, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $products = array_column($scored, 'product');

        $matchedCategories = array_filter($categories, function (array $category) use ($words): bool {
            $title = $this->normalize($category['title']);
            foreach ($words as $w) {
                if (str_contains($title, $w)) {
                    return true;
                }
            }

            return false;
        });

        return view('catalog.search', compact('query', 'products', 'matchedCategories'));
    }

    /** Главы каталога с развёрнутыми категориями. */
    private function chapters(): array
    {
        $categories = config('shop.categories', []);

        return collect(config('shop.chapters', []))
            ->map(fn (array $chapter) => [
                'title'      => $chapter['title'],
                'icon'       => $chapter['icon'],
                'categories' => collect($chapter['categories'])
                    ->filter(fn (string $slug) => isset($categories[$slug]))
                    ->mapWithKeys(fn (string $slug) => [$slug => $categories[$slug]])
                    ->all(),
            ])
            ->all();
    }

    private function normalize(string $text): string
    {
        return str_replace('ё', 'е', mb_strtolower($text));
    }

    /**
     * Простейший «стемминг»: срезаем окончания, чтобы «блесну», «блёсны» и «блесна» совпадали.
     * Слова короче 3 букв («на», «с») игнорируем.
     */
    private function searchWords(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $this->normalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stems = [];
        foreach ($words as $word) {
            $len = mb_strlen($word);
            if ($len < 3) {
                continue;
            }
            $stems[] = match (true) {
                $len >= 6 => mb_substr($word, 0, -2),
                $len >= 4 => mb_substr($word, 0, -1),
                default   => $word,
            };
        }

        return array_values(array_unique($stems));
    }
}
