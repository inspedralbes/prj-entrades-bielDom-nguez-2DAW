<?php

namespace App\Services\Search;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use Illuminate\Support\Facades\Config;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Lectura de preu unitari per esdeveniment (compra per quantitat).
 */
class EventPricingService
{
    /**
     * @return array{event_id: int, unit_price: float, currency: string}|null
     */
    public function unitPricePayload(int $eventId): ?array
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return null;
        }

        $unitPrice = $event->price ?? (float) Config::get('services.order.fixed_event_price_eur', 20.0);

        return [
            'event_id' => $eventId,
            'unit_price' => $unitPrice,
            'currency' => 'EUR',
        ];
    }
}
