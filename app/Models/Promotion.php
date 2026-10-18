<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionType;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string|null $note
 * @property PromotionType $type
 * @property int|null $value
 * @property string|null $code
 * @property int|null $max_uses
 * @property int $used_count
 * @property int|null $min_order_amount
 * @property string $color
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property-read Collection<int, Product> $products
 */
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    public const DEFAULT_COLOR = '#fde68a';

    protected $fillable = [
        'title', 'note', 'type', 'value', 'code', 'max_uses', 'min_order_amount',
        'color', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $attributes = [
        'used_count' => 0,
        'color' => self::DEFAULT_COLOR,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'min_order_amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'promotion_product');
    }

    /** Промокоды сравниваются без учёта регистра и пробелов по краям. */
    public static function normalizeCode(?string $code): ?string
    {
        $code = $code === null ? '' : mb_strtoupper(trim($code));

        return $code === '' ? null : $code;
    }

    /** @return Attribute<string|null, string|null> */
    protected function code(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => self::normalizeCode($value));
    }

    /**
     * Включена и попадает в свой период.
     *
     * @param  Builder<Promotion>  $query
     */
    public function scopeRunning(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();

        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    /**
     * Действует и ею ещё можно воспользоваться (у промокода не исчерпан лимит).
     *
     * @param  Builder<Promotion>  $query
     */
    public function scopeAvailable(Builder $query, ?Carbon $at = null): void
    {
        $query->running($at)
            ->where(fn (Builder $q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    /** Состояние для списка в панели управления. */
    public function status(): string
    {
        $now = now();

        return match (true) {
            ! $this->is_active => 'disabled',
            $this->ends_at !== null && $this->ends_at->lte($now) => 'ended',
            $this->starts_at !== null && $this->starts_at->gt($now) => 'scheduled',
            $this->isExhausted() => 'exhausted',
            default => 'running',
        };
    }

    /** Короткая подпись выгоды: «−15%», «−500 ₽», «доставка от 5 000 ₽». */
    public function benefit(): string
    {
        return match ($this->type) {
            PromotionType::Percent => '−'.$this->value.'%',
            PromotionType::Fixed => '−'.number_format((int) $this->value, 0, ',', ' ').' ₽',
            PromotionType::FreeDelivery => $this->min_order_amount
                ? 'доставка от '.number_format($this->min_order_amount, 0, ',', ' ').' ₽'
                : 'доставка бесплатно',
        };
    }
}
