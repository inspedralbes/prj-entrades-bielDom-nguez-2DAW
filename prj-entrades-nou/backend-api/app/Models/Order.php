<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATE_PENDING_PAYMENT = 'pending_payment';

    public const STATE_PAID = 'paid';

    public const STATE_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'event_id',
        'hold_uuid',
        'state',
        'currency',
        'total_amount',
        'quantity',
    ];

    protected function casts (): array
    {
        return [
            'total_amount' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function user (): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event (): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderLines (): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }
}
