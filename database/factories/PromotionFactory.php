<?php

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Promotion> */
class PromotionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'note' => null,
            'type' => PromotionType::Percent,
            'value' => 10,
            'color' => Promotion::DEFAULT_COLOR,
            'starts_at' => null,
            'ends_at' => null,
            'is_active' => true,
        ];
    }

    public function percent(int $value): static
    {
        return $this->state(['type' => PromotionType::Percent, 'value' => $value]);
    }

    public function code(string $code, int $amount, ?int $maxUses = null): static
    {
        return $this->state([
            'type' => PromotionType::Fixed,
            'value' => $amount,
            'code' => $code,
            'max_uses' => $maxUses,
        ]);
    }

    public function freeDelivery(int $from): static
    {
        return $this->state([
            'type' => PromotionType::FreeDelivery,
            'value' => null,
            'min_order_amount' => $from,
        ]);
    }

    public function ended(): static
    {
        return $this->state(['starts_at' => now()->subDays(10), 'ends_at' => now()->subDay()]);
    }
}
