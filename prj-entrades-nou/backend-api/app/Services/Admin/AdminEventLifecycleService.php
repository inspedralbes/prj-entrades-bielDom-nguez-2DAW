<?php

namespace App\Services\Admin;

//================================ NAMESPACES / IMPORTS ============

use App\Models\Event;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * CRUD i mètriques d’esdeveniments al panell admin.
 */
class AdminEventLifecycleService
{
    public function __construct(
        private readonly AdminAuditLogService $auditLog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function eventsMetrics(): array
    {
        $activeEventsCount = Event::query()->whereNull('hidden_at')->count();

        $salesSum = Order::query()
            ->where('state', Order::STATE_PAID)
            ->sum('total_amount');

        $salesStr = '0.00';
        if ($salesSum !== null) {
            $salesStr = number_format((float) $salesSum, 2, '.', '');
        }

        return [
            'active_events_count' => $activeEventsCount,
            'sales_volume_eur' => $salesStr,
        ];
    }

    public function paginatedEvents(Request $request): LengthAwarePaginator
    {
        $query = Event::query()->orderBy('starts_at', 'desc');

        if ($request->query('hidden') === 'include') {
            //
        } elseif ($request->query('hidden') === 'only') {
            $query->whereNotNull('hidden_at');
        } else {
            $query->whereNull('hidden_at');
        }

        $perPage = (int) $request->query('per_page', 50);
        if ($perPage < 1 || $perPage > 200) {
            $perPage = 50;
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, status: int, body: array<string, mixed>}
     */
    public function updateEvent(Request $request, string $eventId): array
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return ['ok' => false, 'status' => 404, 'body' => ['message' => 'Esdeveniment no trobat']];
        }

        $data = $request->validate([
            'tm_sync_paused' => ['sometimes', 'boolean'],
            'hidden_at' => ['sometimes', 'nullable', 'date'],
            'price' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
            'name' => ['sometimes', 'string', 'max:255'],
            'starts_at' => ['sometimes', 'date'],
            'venue_id' => ['sometimes', 'integer', 'exists:venues,id'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        if (count($data) === 0) {
            return ['ok' => false, 'status' => 422, 'body' => ['message' => 'Envia almenys un camp editable.']];
        }

        if (array_key_exists('tm_sync_paused', $data)) {
            $event->tm_sync_paused = $data['tm_sync_paused'];
        }

        if (array_key_exists('hidden_at', $data)) {
            if ($data['hidden_at'] === null) {
                $event->hidden_at = null;
            } else {
                $event->hidden_at = Carbon::parse($data['hidden_at']);
            }
        }

        if (array_key_exists('price', $data)) {
            $event->price = $data['price'];
        }

        if (array_key_exists('name', $data)) {
            $event->name = $data['name'];
        }

        if (array_key_exists('starts_at', $data)) {
            $event->starts_at = Carbon::parse($data['starts_at']);
        }

        if (array_key_exists('venue_id', $data)) {
            $event->venue_id = (int) $data['venue_id'];
        }

        if (array_key_exists('image_url', $data)) {
            $event->image_url = $data['image_url'];
        }

        if (array_key_exists('category', $data)) {
            $event->category = $data['category'];
        }

        $event->save();

        $changedFields = implode(', ', array_keys($data));
        $this->auditLog->record(
            adminUserId: (int) $request->user()->id,
            action: 'update',
            entityType: 'event',
            entityId: (int) $event->id,
            summary: 'Esdeveniment #'.$event->id.' actualitzat: '.$changedFields,
            ipAddress: $request->ip(),
        );

        return [
            'ok' => true,
            'body' => [
                'id' => $event->id,
                'external_tm_id' => $event->external_tm_id,
                'name' => $event->name,
                'venue_id' => $event->venue_id,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'category' => $event->category,
                'price' => $event->price,
                'image_url' => $event->image_url,
                'tm_sync_paused' => $event->tm_sync_paused,
                'hidden_at' => $event->hidden_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{ok: true, body: array<string, mixed>, status: int}|array{ok: false, status: int, body: array<string, mixed>}
     */
    public function storeEvent(Request $request): array
    {
        $data = $request->validate([
            'external_tm_id' => ['required', 'string', 'max:255', 'unique:events,external_tm_id'],
            'name' => ['required', 'string', 'max:255'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'starts_at' => ['required', 'date'],
            'category' => ['nullable', 'string', 'max:100'],
            'hold_ttl_seconds' => ['nullable', 'integer', 'min:60', 'max:3600'],
            'hidden_at' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0.01', 'max:999999.99'],
            'image_url' => ['nullable', 'string', 'max:2000'],
        ]);

        $event = new Event;
        $event->external_tm_id = $data['external_tm_id'];
        $event->name = $data['name'];
        $event->venue_id = $data['venue_id'];
        $event->starts_at = Carbon::parse($data['starts_at']);
        $event->category = $data['category'] ?? null;
        $event->hold_ttl_seconds = $data['hold_ttl_seconds'] ?? 240;
        $event->hidden_at = isset($data['hidden_at']) ? Carbon::parse($data['hidden_at']) : null;
        $event->tm_sync_paused = true;
        if (isset($data['price'])) {
            $event->price = $data['price'];
        } else {
            $event->price = (float) Config::get('services.order.fixed_event_price_eur', 20.0);
        }
        if (isset($data['image_url'])) {
            $event->image_url = $data['image_url'];
        }
        $event->save();

        return [
            'ok' => true,
            'status' => 201,
            'body' => [
                'id' => $event->id,
                'external_tm_id' => $event->external_tm_id,
                'name' => $event->name,
                'venue_id' => $event->venue_id,
                'starts_at' => $event->starts_at->toIso8601String(),
                'category' => $event->category,
                'price' => $event->price,
                'image_url' => $event->image_url,
                'tm_sync_paused' => $event->tm_sync_paused,
                'hidden_at' => $event->hidden_at?->toIso8601String(),
                'created_at' => $event->created_at->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array{ok: true, body: array<string, mixed>}|array{ok: false, status: int, body: array<string, mixed>}
     */
    public function destroyEvent(Request $request, int $eventId): array
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return ['ok' => false, 'status' => 404, 'body' => ['message' => 'Esdeveniment no trobat']];
        }

        $force = $request->query('force') === '1';
        if ($force) {
            $event->delete();

            return ['ok' => true, 'body' => ['message' => 'Esdeveniment eliminat definitivament de la base de dades']];
        }

        $event->hidden_at = now();
        $event->save();

        return ['ok' => true, 'body' => ['message' => 'Esdeveniment ocultat (hidden_at). No es mostra al catàleg públic.']];
    }
}
