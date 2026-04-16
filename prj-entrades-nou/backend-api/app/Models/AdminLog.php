<?php

namespace App\Models;

//================================ NAMESPACES / IMPORTS ============

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class AdminLog extends Model
{
    protected $fillable = [
        'admin_user_id',
        'action',
        'entity_type',
        'entity_id',
        'summary',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
