<?php

namespace App\Models;

//================================ NAMESPACES / IMPORTS ============

use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
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
        'tm_sync_paused',
        'tm_url',
        'price',
        'tm_category',
        'is_large_event',
        'image_url',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'hidden_at' => 'datetime',
            'seat_layout' => 'array',
            'tm_sync_paused' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
