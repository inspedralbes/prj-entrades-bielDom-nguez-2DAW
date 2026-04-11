<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'gemini_personalization_enabled',
    ];

    protected function casts (): array
    {
        return [
            'gemini_personalization_enabled' => 'boolean',
        ];
    }

    public function user (): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
