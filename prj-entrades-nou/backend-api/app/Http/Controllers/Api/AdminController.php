<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use App\Services\Admin\AdminAuditLogService;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Socket\InternalSocketNotifier;
use App\Services\Ticketmaster\TicketmasterEventImportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Esborranys T035: panell / import — respostes mínimes fins a T052.
 */
class AdminController extends Controller
{
    public function __construct (
        private readonly InternalSocketNotifier $socketNotifier,
        private readonly TicketmasterEventImportService $ticketmasterEventImportService,
        private readonly AdminDashboardMetricsService $adminDashboardMetrics,
        private readonly AdminAuditLogService $adminAuditLog,
    ) {}

    public function summary (Request $request): JsonResponse
    {
        $payload = $this->adminDashboardMetrics->buildFullDashboardPayload();

        $this->socketNotifier->emitMetricsStub($payload);

        return response()->json($payload);
    }

    public function discoverySync (Request $request): JsonResponse
    {
        $maxPages = null;
        if ($request->query('max_pages') !== null) {
            $maxPages = (int) $request->query('max_pages');
        }

        $result = $this->ticketmasterEventImportService->sync($maxPages);

        $this->adminDashboardMetrics->recordDiscoverySyncResult($result);

        $user = $request->user();
        if ($user !== null) {
            $msg = 'Sincronització Discovery Ticketmaster completada (inserits: '.$result['inserted'].', pàgines: '.$result['pages_fetched'].').';
            $this->adminAuditLog->record(
                (int) $user->id,
                'discovery_sync',
                'TicketmasterDiscovery',
                null,
                $msg,
                $request->ip()
            );
        }

        try {
            $snap = $this->adminDashboardMetrics->buildFullDashboardPayload();
            $this->socketNotifier->emitMetricsStub($snap);
        } catch (\Throwable) {
        }

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
    public function updateEvent (Request $request, string $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
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
            return response()->json([
                'message' => 'Envia almenys un camp editable.',
            ], 422);
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

        $actor = $request->user();
        if ($actor !== null) {
            $this->adminAuditLog->record(
                (int) $actor->id,
                'updated',
                'Event',
                (int) $event->id,
                'S\'ha actualitzat l\'esdeveniment «'.$event->name.'».',
                $request->ip()
            );
        }

        return response()->json([
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
        ]);
    }

    /**
     * KPIs per al panell d’esdeveniments: recompte actius (futurs i visibles) i volum de vendes (ingressos acumulats pagats).
     */
    public function eventsMetrics (Request $request): JsonResponse
    {
        $now = Carbon::now();

        $activeEventsCount = Event::query()
            ->whereNull('hidden_at')
            ->where('starts_at', '>=', $now)
            ->count();

        $sum = Order::query()
            ->where('state', Order::STATE_PAID)
            ->sum('total_amount');

        $salesVolumeEur = '0.00';
        if ($sum !== null) {
            $salesVolumeEur = number_format((float) $sum, 2, '.', '');
        }

        return response()->json([
            'active_events_count' => $activeEventsCount,
            'sales_volume_eur' => $salesVolumeEur,
        ]);
    }

    public function index (Request $request): JsonResponse
    {
        $query = Event::query()->with('venue')->orderBy('starts_at', 'desc');

        // Visibilitat amb `hidden_at` (el model Event no usa SoftDeletes; `withTrashed()` provocava 500).
        // hidden=include: sense filtre (tot el catàleg al panell); only: només ocults; per defecte: només visibles.
        $hidden = $request->query('hidden');
        if ($hidden === 'only') {
            $query->whereNotNull('hidden_at');
        } elseif ($hidden !== 'include') {
            $query->whereNull('hidden_at');
        }

        $perPage = (int) $request->query('per_page', 50);
        if ($perPage < 1 || $perPage > 200) {
            $perPage = 50;
        }

        $events = $query->paginate($perPage);

        return response()->json($events);
    }

    public function store (Request $request): JsonResponse
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

        $event = new Event();
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

        $actor = $request->user();
        if ($actor !== null) {
            $this->adminAuditLog->record(
                (int) $actor->id,
                'created',
                'Event',
                (int) $event->id,
                'S\'ha creat l\'esdeveniment «'.$event->name.'».',
                $request->ip()
            );
        }

        return response()->json([
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
        ], 201);
    }

    public function destroy (Request $request, int $eventId): JsonResponse
    {
        $event = Event::query()->find($eventId);
        if ($event === null) {
            return response()->json(['message' => 'Esdeveniment no trobat'], 404);
        }

        $force = $request->query('force') === '1';
        $actor = $request->user();
        if ($force) {
            $name = $event->name;
            $eid = (int) $event->id;
            $event->delete();
            if ($actor !== null) {
                $this->adminAuditLog->record(
                    (int) $actor->id,
                    'deleted',
                    'Event',
                    $eid,
                    'S\'ha eliminat definitivament l\'esdeveniment «'.$name.'».',
                    $request->ip()
                );
            }

            return response()->json(['message' => 'Esdeveniment eliminat definitivament de la base de dades']);
        }

        $event->hidden_at = now();
        $event->save();
        if ($actor !== null) {
            $this->adminAuditLog->record(
                (int) $actor->id,
                'updated',
                'Event',
                (int) $event->id,
                'S\'ha ocultat l\'esdeveniment «'.$event->name.'» del catàleg públic.',
                $request->ip()
            );
        }

        return response()->json(['message' => 'Esdeveniment ocultat (hidden_at). No es mostra al catàleg públic.']);
    }
}
