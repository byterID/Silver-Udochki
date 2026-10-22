<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int $amount
 * @property int $balance_after
 * @property WalletReason $reason
 * @property string|null $reference
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read Wallet $wallet
 */
class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id', 'amount', 'balance_after', 'reason', 'reference', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'reason' => WalletReason::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
