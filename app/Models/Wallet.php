<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Кошелёк пользователя. Баланс меняет только WalletKeeper, каждое изменение
 * — строка в wallet_transactions.
 *
 * @property int $id
 * @property int $user_id
 * @property int $balance
 * @property-read User $user
 */
class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WalletTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
