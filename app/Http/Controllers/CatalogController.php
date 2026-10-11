<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /** Главная: лавка с продавцом. */
    public function home(Request $request): View
    {
        $user = $request->user();
        $promos = $this->activePromos();
        $catalog = $this->catalogData();

        $front = [
            'greeting' => $user ? 'А, '.$user->name.'! Рад снова видеть.' : 'Здравствуй, путник!',
            'questions' => config('shop.questions', []),
            'idle' => config('shop.idle_replies', []),
            'promos' => array_column($promos, 'title'),
            'searchUrl' => route('catalog.search'),
            'firstChapter' => array_key_first($catalog['chapters']),
            'catalog' => $catalog,
            'options' => [
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

        /** @var array<int, array<string, mixed>> $promos */
        $promos = config('shop.promos', []);

        return collect($promos)
            ->filter(function (array $p) use ($now): bool {
                $starts = isset($p['starts_at']) ? Carbon::parse($p['starts_at']) : null;
                $ends = isset($p['ends_at']) ? Carbon::parse($p['ends_at']) : null;

                return (! $starts || $starts->lte($now)) && (! $ends || $ends->gt($now));
            })
            ->map(fn (array $p) => [
                'title' => $p['title'],
                'note' => $p['note'] ?? null,
                'ends' => isset($p['ends_at']) ? Carbon::parse($p['ends_at'])->getTimestampMs() : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Данные для «монитора»: главы, категории и товары внутри них.
     *
     * @return array{chapters: array<string, array<string, mixed>>, categories: array<string, array<string, mixed>>}
     */
    private function catalogData(): array
    {
        $chapters = Chapter::query()
            ->orderBy('sort')
            ->with([
                'categories',
                'categories.products' => fn ($query) => $query->where('is_active', true)->orderBy('id'),
            ])
            ->get();

        $data = ['chapters' => [], 'categories' => []];

        foreach ($chapters as $chapter) {
            $data['chapters'][$chapter->slug] = [
                'title' => $chapter->title,
                'icon' => $chapter->icon,
                'categories' => $chapter->categories->pluck('slug')->all(),
            ];

            foreach ($chapter->categories as $category) {
                $data['categories'][$category->slug] = [
                    'title' => $category->title,
                    'icon' => $category->icon,
                    'description' => $category->description,
                    'url' => route('catalog.category', $category->slug),
                    'products' => $category->products
                        ->map(fn (Product $p) => [
                            'id' => $p->id,
                            'name' => $p->name,
                            'price' => $p->price,
                            'icon' => $p->icon,
                        ])
                        ->values()
                        ->all(),
                ];
            }
        }

        return $data;
    }

    public function category(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();
        $products = $category->products()->active()->orderBy('id')->get();

        return view('catalog.category', compact('slug', 'category', 'products'));
    }

    public function search(Request $request): View
    {
        $raw = $request->query('q');
        $query = Str::limit(trim(is_string($raw) ? $raw : ''), 100, '');
        $words = $this->searchWords($query);

        $products = collect();
        $matchedCategories = [];

        if ($words !== []) {
            // Отбор в PHP: SQLite (тесты) не умеет lower() для кириллицы.
            $products = Product::query()
                ->active()
                ->with('category')
                ->orderBy('id')
                ->get()
                ->map(fn (Product $p) => [
                    'score' => $this->score($this->normalize(implode(' ', [
                        $p->name,
                        $p->category->title,
                        implode(' ', $p->tags ?? []),
                    ])), $words),
                    'product' => $p,
                ])
                ->filter(fn (array $row) => $row['score'] > 0)
                ->sortByDesc('score')
                ->pluck('product')
                ->values();

            $matchedCategories = Category::query()
                ->orderBy('sort')
                ->get()
                ->filter(fn (Category $c) => $this->score($this->normalize($c->title), $words) > 0)
                ->keyBy('slug')
                ->all();
        }

        return view('catalog.search', compact('query', 'products', 'matchedCategories'));
    }

    /** @param list<string> $words */
    private function score(string $haystack, array $words): int
    {
        return count(array_filter($words, fn (string $w) => str_contains($haystack, $w)));
    }

    private function normalize(string $text): string
    {
        return str_replace('ё', 'е', mb_strtolower($text));
    }

    /**
     * Простейший «стемминг»: срезаем окончания, чтобы «блесну», «блёсны» и «блесна» совпадали.
     *
     * @return list<string>
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
                default => $word,
            };
        }

        return array_values(array_unique($stems));
    }
}
