<?php

namespace App\Services\Ticketmaster\Venue;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Venue;
use Illuminate\Support\Facades\DB;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Persistència de recintes Ticketmaster (`venues.external_tm_id`) i coordenades PostGIS.
 */
class TicketmasterVenueUpsertService
{
    /**
     * A. Resol identificador TM i nom/adreça.
     * B. Crea o actualitza la fila Eloquent.
     * C. Actualitza `location` amb ST_MakePoint si hi ha lat/lng i el driver és pgsql.
     *
     * @param  array<string, mixed>  $v0
     */
    public function upsertVenueFromPayload(array $v0): ?Venue
    {
        $tmVenueId = null;
        if (isset($v0['id']) && is_string($v0['id'])) {
            $tmVenueId = $v0['id'];
        }
        if ($tmVenueId === null || $tmVenueId === '') {
            return null;
        }

        $venueName = 'Sense nom';
        if (isset($v0['name']) && is_string($v0['name']) && $v0['name'] !== '') {
            $venueName = $v0['name'];
        }

        $address = null;
        $city = null;

        if (isset($v0['address']) && is_array($v0['address']) && isset($v0['address']['line1'])) {
            $address = (string) $v0['address']['line1'];
        }

        if (isset($v0['city']) && is_array($v0['city']) && isset($v0['city']['name'])) {
            $city = (string) $v0['city']['name'];
        }

        $venue = Venue::query()->where('external_tm_id', $tmVenueId)->first();
        if ($venue === null) {
            $venue = new Venue;
            $venue->external_tm_id = $tmVenueId;
            $venue->name = $venueName;
            $venue->address = $address;
            $venue->city = $city;
            $venue->save();
        } else {
            $venue->name = $venueName;
            if ($address !== null) {
                $venue->address = $address;
            }
            if ($city !== null) {
                $venue->city = $city;
            }
            $venue->save();
        }

        $lat = null;
        $lng = null;
        if (isset($v0['location']) && is_array($v0['location'])) {
            $loc = $v0['location'];
            if (isset($loc['latitude'])) {
                $lat = (float) $loc['latitude'];
            }
            if (isset($loc['longitude'])) {
                $lng = (float) $loc['longitude'];
            }
        }

        $this->applyVenueLocation($venue, $lat, $lng);

        return $venue;
    }

    //================================ LÒGICA PRIVADA ================

    private function applyVenueLocation(Venue $venue, ?float $lat, ?float $lng): void
    {
        if ($lat === null || $lng === null) {
            return;
        }
        if (config('database.default') !== 'pgsql') {
            return;
        }

        DB::statement(
            'UPDATE venues SET location = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, updated_at = ? WHERE id = ?',
            [$lng, $lat, now(), $venue->id]
        );
    }
}
