<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    protected $fillable = [
        'external_tm_id',
        'name',
        'hold_ttl_seconds',
        'venue_id',
        'starts_at',
        'hidden_at',
        'category',
        'seat_layout',
    ];

    protected function casts (): array
    {
        return [
            'starts_at' => 'datetime',
            'hidden_at' => 'datetime',
            'seat_layout' => 'array',
        ];
    }

    public function venue (): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function zones (): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
