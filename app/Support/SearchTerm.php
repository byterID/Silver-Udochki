<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class SearchTerm
{
    // % и _ выкидываем: у LIKE в SQLite нет экранирования по умолчанию
    public static function from(mixed $raw): string
    {
        $term = is_string($raw) ? str_replace(['%', '_'], '', $raw) : '';

        return Str::limit(trim($term), 100, '');
    }
}
