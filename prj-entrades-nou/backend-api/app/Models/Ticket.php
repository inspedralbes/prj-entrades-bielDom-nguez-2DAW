<?php

namespace App\Models;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class Ticket extends Model
{
    public const STATUS_VENUDA = 'venuda';

    public const STATUS_UTILITZADA = 'utilitzada';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'public_uuid',
        'order_line_id',
        'status',
        'qr_payload_ref',
        'jwt_expires_at',
        'used_at',
        'validator_id',
    ];

    protected function casts(): array
    {
        return [
            'jwt_expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }
}
