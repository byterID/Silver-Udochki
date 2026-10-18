<?php

declare(strict_types=1);

namespace App\Enums;

/** Причина возврата из формы «Оформить возврат». */
enum ReturnReason: string
{
    case NotSuitable = 'not_suitable';
    case Defect = 'defect';
    case NotAsDescribed = 'not_as_described';
    case WrongItem = 'wrong_item';
    case ChangedMind = 'changed_mind';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NotSuitable => 'Не подошёл',
            self::Defect => 'Брак или поломка',
            self::NotAsDescribed => 'Не соответствует описанию',
            self::WrongItem => 'Привезли не тот товар',
            self::ChangedMind => 'Передумал',
            self::Other => 'Другое',
        };
    }

    /** Для «Другое» покупатель обязан описать причину сам. */
    public function needsComment(): bool
    {
        return $this === self::Other;
    }

    /** @return list<array{value: string, label: string}> для выпадающего списка формы */
    public static function options(): array
    {
        return array_map(fn (self $reason) => ['value' => $reason->value, 'label' => $reason->label()], self::cases());
    }
}
