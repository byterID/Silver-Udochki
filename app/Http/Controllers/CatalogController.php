<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ShopCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /** Главная: лавка с продавцом. Монитор-каталог подключается в layouts/app. */
    public function home(Request $request, ShopCatalog $shop): View
    {
        $user = $request->user();
        $promos = $shop->activePromos();

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
                default => $word,
            };
        }

        return array_values(array_unique($stems));
    }
}
