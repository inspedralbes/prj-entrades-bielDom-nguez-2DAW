<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends Model
{
    /** @use HasFactory<\Database\Factories\SeatFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'zone_id',
        'external_seat_key',
        'status',
        'current_hold_id',
        'held_until',
    ];

    protected function casts (): array
    {
        return [
            'held_until' => 'datetime',
        ];
    }

    public function event (): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function zone (): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }
}
