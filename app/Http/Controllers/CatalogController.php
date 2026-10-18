<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\ShopCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(private readonly ShopCatalog $shop) {}

    /** Главная: лавка с продавцом. Каталог для монитора отдаёт CatalogMonitorComposer. */
    public function home(Request $request): View
    {
        $user = $request->user();
        $promos = $this->shop->activePromos();

        $front = [
            'greeting' => $user ? 'А, '.$user->name.'! Рад снова видеть.' : 'Здравствуй, путник!',
            'questions' => config('shop.questions', []),
            'idle' => config('shop.idle_replies', []),
            'promos' => array_column($promos, 'title'),
            'searchUrl' => route('catalog.search'),
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

    public function category(string $slug): View
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();
        $products = $category->products()->active()->orderBy('id')->get()
            ->map(fn (Product $p) => $this->shop->presentProduct($p, $category->icon));

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
                ->map(fn (array $row) => $this->shop->presentProduct($row['product'], $row['product']->category->icon))
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
