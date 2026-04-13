<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    /** @use HasFactory<\Database\Factories\VenueFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'external_tm_id',
    ];

    public function events (): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
