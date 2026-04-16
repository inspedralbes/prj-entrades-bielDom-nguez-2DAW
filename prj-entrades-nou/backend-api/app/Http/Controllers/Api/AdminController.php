<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardMetricsService;
use App\Services\Admin\AdminDashboardSummaryService;
use App\Services\Admin\AdminEventLifecycleService;
use App\Services\Socket\InternalSocketNotifier;
use App\Services\Ticketmaster\TicketmasterEventImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Esborranys T035: panell / import — respostes mínimes fins a T052.
 */
class AdminController extends Controller
{
    public function __construct(
        private readonly InternalSocketNotifier $socketNotifier,
        private readonly TicketmasterEventImportService $ticketmasterEventImportService,
        private readonly AdminDashboardMetricsService $adminDashboardMetrics,
        private readonly AdminDashboardSummaryService $adminDashboardSummary,
        private readonly AdminEventLifecycleService $adminEventLifecycle,
    ) {
        //
    }

    public function summary(Request $request): JsonResponse
    {
        $payload = $this->adminDashboardSummary->buildSummaryPayload();

        $this->socketNotifier->emitMetricsStub($payload);

        return response()->json($payload);
    }

    public function discoverySync(Request $request): JsonResponse
    {
        $maxPages = null;
        if ($request->query('max_pages') !== null) {
            $maxPages = (int) $request->query('max_pages');
        }

        $result = $this->ticketmasterEventImportService->sync($maxPages);

        $this->adminDashboardMetrics->recordDiscoverySyncResult($result);

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
        $result = $this->adminEventLifecycle->updateEvent($request, $eventId);
        if ($result['ok'] === false) {
            return response()->json($result['body'], $result['status']);
        }

        return response()->json($result['body']);
    }

    /**
     * Mètriques resumides per al capçalera de la llista d’esdeveniments (panell admin).
     */
    public function eventsMetrics(Request $request): JsonResponse
    {
        return response()->json($this->adminEventLifecycle->eventsMetrics());
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->adminEventLifecycle->paginatedEvents($request));
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->adminEventLifecycle->storeEvent($request);
        if ($result['ok'] === false) {
            return response()->json($result['body'], $result['status']);
        }

        return response()->json($result['body'], $result['status']);
    }

    public function destroy(Request $request, int $eventId): JsonResponse
    {
        $result = $this->adminEventLifecycle->destroyEvent($request, $eventId);
        if ($result['ok'] === false) {
            return response()->json($result['body'], $result['status']);
        }

        return response()->json($result['body']);
    }
}
