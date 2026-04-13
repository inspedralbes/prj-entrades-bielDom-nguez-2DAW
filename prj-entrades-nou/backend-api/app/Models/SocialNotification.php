<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feed social (G/H): notificacions persistides; taula {@code social_notifications} per no col·lidir amb Notifiable de Laravel.
 */
class SocialNotification extends Model
{
    protected $table = 'social_notifications';

    protected $fillable = [
        'user_id',
        'actor_user_id',
        'type',
        'payload',
        'read_at',
    ];

    protected function casts (): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function actor (): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
