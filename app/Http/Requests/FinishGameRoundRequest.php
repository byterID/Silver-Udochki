<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Game\ShotJudge;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

// Только форма данных, правдоподобие выстрелов проверяет ShotJudge
/** @phpstan-import-type Shot from ShotJudge */
class FinishGameRoundRequest extends FormRequest
{
    public const MAX_SHOTS = 300;

    public function authorize(): bool
    {
        return true; // владельца проверяет can:play на маршруте
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'shots' => ['present', 'array', 'list', 'max:'.self::MAX_SHOTS],
            'shots.*' => ['array:t,x,y'],
            'shots.*.t' => ['required', 'integer', 'min:0'],
            'shots.*.x' => ['required', 'numeric'],
            'shots.*.y' => ['required', 'numeric'],
        ];
    }

    /** @return list<Shot> */
    public function shots(): array
    {
        return array_map(fn (array $shot) => [
            't' => (int) $shot['t'],
            'x' => (float) $shot['x'],
            'y' => (float) $shot['y'],
        ], array_values($this->validated('shots')));
    }
}
