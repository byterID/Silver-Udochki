<?php

declare(strict_types=1);

namespace App\Http\Requests\ControlPanel;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePromotionRequest extends FormRequest
{
    public const MAX_PRODUCTS = 2000;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Promotion::class) ?? false;
    }

    // У новой акции null, UpdatePromotionRequest возвращает id редактируемой
    protected function promotionId(): ?int
    {
        return null;
    }

    public function rules(): array
    {
        $type = PromotionType::tryFrom((string) $this->input('type'));

        return [
            'title' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(PromotionType::class)],
            'color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', ...($this->filled('starts_at') ? ['after:starts_at'] : [])],
            'value' => [
                Rule::requiredIf(in_array($type, [PromotionType::Percent, PromotionType::Fixed], true)),
                'nullable', 'integer',
                $type === PromotionType::Percent ? 'between:1,99' : 'between:1,1000000',
            ],
            'code' => [
                Rule::requiredIf($type === PromotionType::Fixed),
                'nullable', 'string', 'min:3', 'max:32', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('promotions', 'code')->ignore($this->promotionId()),
            ],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'min_order_amount' => [
                Rule::requiredIf($type === PromotionType::FreeDelivery),
                'nullable', 'integer', 'min:0', 'max:10000000',
            ],
            'products' => [
                Rule::requiredIf($type === PromotionType::Percent),
                'array', 'max:'.self::MAX_PRODUCTS,
            ],
            'products.*' => ['integer', 'distinct'],
        ];
    }

    // Чужие для выбранного вида поля обнуляем, чтобы не оставались в БД
    protected function prepareForValidation(): void
    {
        $type = PromotionType::tryFrom((string) $this->input('type'));
        $code = $this->input('code');
        $color = $this->input('color');

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'color' => is_string($color) ? mb_strtolower(trim($color)) : $color,
            'code' => $type === PromotionType::Fixed && is_string($code) ? Promotion::normalizeCode($code) : null,
            'value' => $type === PromotionType::FreeDelivery ? null : $this->input('value'),
            'max_uses' => $type === PromotionType::Fixed ? $this->input('max_uses') : null,
            'min_order_amount' => $type === PromotionType::FreeDelivery ? $this->input('min_order_amount') : null,
            'products' => $type === PromotionType::Percent ? $this->input('products', []) : [],
        ]);
    }

    // У товара может быть только одна акция (product_id уникален в promotion_product)
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('products') || $validator->errors()->has('products.*')) {
                return;
            }

            $ids = $this->productIds();
            if ($ids === []) {
                return;
            }

            $found = DB::table('products')->whereIn('id', $ids)->count();
            if ($found !== count($ids)) {
                $validator->errors()->add('products', 'Некоторые товары не найдены. Обновите страницу и выберите заново.');

                return;
            }

            $taken = DB::table('promotion_product')
                ->join('promotions', 'promotions.id', '=', 'promotion_product.promotion_id')
                ->join('products', 'products.id', '=', 'promotion_product.product_id')
                ->whereIn('promotion_product.product_id', $ids)
                ->when($this->promotionId(), fn ($q, $id) => $q->where('promotion_product.promotion_id', '!=', $id))
                ->orderBy('products.name')
                ->get(['products.name', 'promotions.title']);

            if ($taken->isNotEmpty()) {
                $list = $taken->take(5)->map(fn ($row) => "«{$row->name}» (акция «{$row->title}»)")->implode(', ');
                $more = $taken->count() > 5 ? ' и ещё '.($taken->count() - 5) : '';

                $validator->errors()->add(
                    'products',
                    "К товару может применяться только одна акция. Уже заняты: {$list}{$more}.",
                );
            }
        }];
    }

    /** @return list<int> */
    public function productIds(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = (array) $this->input('products', []);

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** @return array<string, mixed> */
    public function promotionData(): array
    {
        $data = $this->safe()->except('products');
        $data['type'] = PromotionType::from($data['type']);

        return $data;
    }

    public function attributes(): array
    {
        return [
            'title' => 'название',
            'note' => 'подпись',
            'type' => 'вид акции',
            'color' => 'цвет',
            'starts_at' => 'начало',
            'ends_at' => 'окончание',
            'value' => 'размер скидки',
            'code' => 'промокод',
            'max_uses' => 'лимит использований',
            'min_order_amount' => 'порог заказа',
            'products' => 'товары',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Заполните поле «:attribute».',
            'products.required' => 'Выберите хотя бы один товар.',
            'integer' => 'В поле «:attribute» нужно целое число.',
            'between' => 'Поле «:attribute» должно быть от :min до :max.',
            'min' => 'Поле «:attribute» не меньше :min.',
            'max' => 'Поле «:attribute» не больше :max.',
            'products.max' => 'В одной акции не больше :max товаров.',
            'code.regex' => 'В промокоде только латинские буквы, цифры, дефис и подчёркивание.',
            'color.regex' => 'Цвет нужен в виде #rrggbb.',
            'unique' => 'Такой :attribute уже есть.',
            'date' => 'В поле «:attribute» нужна дата.',
            'ends_at.after' => 'Окончание должно быть позже начала.',
        ];
    }
}
