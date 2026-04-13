<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Services\Socket\InternalSocketNotifier;
use App\Services\Ticketmaster\TicketmasterEventImportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Esborranys T035: panell / import — respostes mínimes fins a T052.
 */
class AdminController extends Controller
{
    public function __construct(
        private readonly InternalSocketNotifier $socketNotifier,
        private readonly TicketmasterEventImportService $ticketmasterEventImportService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $this->socketNotifier->emitMetricsStub([
            'events_total' => Event::query()->count(),
            'generated_at' => now()->toIso8601String(),
        ]);

        return response()->json([
            'stub' => true,
            'events_total' => Event::query()->count(),
            'orders_paid' => Order::query()->where('state', Order::STATE_PAID)->count(),
        ]);
    }

    public function discoverySync(Request $request): JsonResponse
    {
        $maxPages = null;
        if ($request->query('max_pages') !== null) {
            $maxPages = (int) $request->query('max_pages');
        }

        $result = $this->ticketmasterEventImportService->sync($maxPages);

        return response()->json([
            'status' => 'completed',
            'stub' => false,
            'message' => 'Sincronització Discovery aplicada a la base de dades local.',
            'inserted' => $result['inserted'],
            'skipped_existing' => $result['skipped_existing'],
            'skipped_no_venue' => $result['skipped_no_venue'],
            'pages_fetched' => $result['pages_fetched'],
            'errors' => $result['errors'],
        ], 200);
    }

    /**
     * Actualització parcial d'esdeveniment: visibilitat (hidden_at), bloqueig de sync TM (tm_sync_paused) i preu (des del panell admin).
     * El sync Ticketmaster no sobreescriu files existents (només afegeix nous); el preu editat aquí es manté.
     */
    public function updateEvent(Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $data = $request->validate([
            'tm_sync_paused' => ['sometimes', 'boolean'],
            'hidden_at' => ['sometimes', 'nullable', 'date'],
            'price' => ['sometimes', 'numeric', 'min:0.01', 'max:999999.99'],
        ]);

        $hasTm = array_key_exists('tm_sync_paused', $data);
        $hasHidden = array_key_exists('hidden_at', $data);
        $hasPrice = array_key_exists('price', $data);
        if ($hasTm === false && $hasHidden === false && $hasPrice === false) {
            return response()->json([
                'message' => 'Envia almenys un camp: tm_sync_paused, hidden_at o price.',
            ], 422);
        }

        if ($hasTm === true) {
            $event->tm_sync_paused = $data['tm_sync_paused'];
        }

        if ($hasHidden === true) {
            if ($data['hidden_at'] === null) {
                $event->hidden_at = null;
            } else {
                $event->hidden_at = Carbon::parse($data['hidden_at']);
            }
        }

        if ($hasPrice === true) {
            $event->price = $data['price'];
        }

        $event->save();

        return response()->json([
            'id' => $event->id,
            'external_tm_id' => $event->external_tm_id,
            'name' => $event->name,
            'price' => $event->price,
            'tm_sync_paused' => $event->tm_sync_paused,
            'hidden_at' => $event->hidden_at?->toIso8601String(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()->orderBy('starts_at', 'desc');

        if ($request->query('hidden') === 'include') {
            $query->withTrashed();
        } elseif ($request->query('hidden') === 'only') {
            $query->whereNotNull('hidden_at');
        } else {
            $query->whereNull('hidden_at');
        }

        $perPage = (int) $request->query('per_page', 50);
        if ($perPage < 1 || $perPage > 200) {
            $perPage = 50;
        }

        $events = $query->paginate($perPage);

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'external_tm_id' => ['required', 'string', 'max:255', 'unique:events,external_tm_id'],
            'name' => ['required', 'string', 'max:255'],
            'venue_id' => ['required', 'integer', 'exists:venues,id'],
            'starts_at' => ['required', 'date'],
            'category' => ['nullable', 'string', 'max:100'],
            'hold_ttl_seconds' => ['nullable', 'integer', 'min:60', 'max:3600'],
            'hidden_at' => ['nullable', 'date'],
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
        $event->save();

        return response()->json([
            'id' => $event->id,
            'external_tm_id' => $event->external_tm_id,
            'name' => $event->name,
            'venue_id' => $event->venue_id,
            'starts_at' => $event->starts_at->toIso8601String(),
            'category' => $event->category,
            'tm_sync_paused' => $event->tm_sync_paused,
            'hidden_at' => $event->hidden_at?->toIso8601String(),
            'created_at' => $event->created_at->toIso8601String(),
        ], 201);
    }

    public function destroy(Request $request, int $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $force = $request->query('force') === '1';
        if ($force) {
            $event->forceDelete();
        } else {
            $event->delete();
        }

        return response()->json(['message' => 'Esdeveniment eliminat'.($force ? ' definitivament' : '')]);
    }
}
