<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    protected function casts (): array
    {
        return [
            'entity_id' => 'integer',
        ];
    }

    public function adminUser (): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
