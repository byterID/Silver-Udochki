<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';       // принята, ждёт воркера
    case Processing = 'processing'; // воркер взял в работу
    case Done = 'done';             // готово, есть результат
    case Failed = 'failed';         // упала с ошибкой

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'В очереди',
            self::Processing => 'Обрабатывается',
            self::Done => 'Готово',
            self::Failed => 'Ошибка',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Failed], true);
    }
}
