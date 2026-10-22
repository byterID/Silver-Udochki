<?php

declare(strict_types=1);

namespace App\Tasks;

use App\Models\Task;
use Illuminate\Support\Facades\Storage;

final class CsvResult
{
    // «;» и BOM: русский Excel открывает такой файл без настройки
    /** @param iterable<list<string|int>> $rows */
    public static function save(Task $task, iterable $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '');
        }

        rewind($stream);
        $path = "results/{$task->id}.csv";
        Storage::disk('local')->put($path, $stream);
        fclose($stream);

        return $path;
    }
}
