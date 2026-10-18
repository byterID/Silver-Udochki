<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use App\Models\Promotion;

class UpdatePromotionRequest extends StorePromotionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->promotion()) ?? false;
    }

    protected function promotionId(): ?int
    {
        return $this->promotion()->id;
    }

    private function promotion(): Promotion
    {
        /** @var Promotion $promotion */
        $promotion = $this->route('promotion');

        return $promotion;
    }
}
