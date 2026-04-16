<?php

namespace App\Models;

//================================ NAMESPACES / IMPORTS ============

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'external_tm_id',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
