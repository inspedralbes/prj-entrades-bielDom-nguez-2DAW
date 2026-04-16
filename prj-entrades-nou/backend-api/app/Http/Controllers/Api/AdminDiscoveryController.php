<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Services\Ticketmaster\TicketmasterDiscoveryEventsClient;
use App\Services\Ticketmaster\TicketmasterEventImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========


/**
 * Cerca i import puntual Ticketmaster Discovery (panell admin).
 */
class AdminDiscoveryController extends Controller
{
    public function __construct(
        private readonly TicketmasterDiscoveryEventsClient $discoveryClient,
        private readonly TicketmasterEventImportService $ticketmasterEventImportService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $keyword = (string) $request->query('keyword', '');
        if (trim($keyword) === '') {
            return response()->json(['message' => 'Paràmetre keyword obligatori'], 422);
        }

        $page = (int) $request->query('page', 0);
        if ($page < 0) {
            $page = 0;
        }

        $size = (int) $request->query('size', 20);
        if ($size < 1) {
            $size = 20;
        }
        if ($size > 50) {
            $size = 50;
        }

        $country = (string) Config::get('services.ticketmaster.sync_country', 'ES');
        $payload = $this->discoveryClient->searchEventsPage($keyword, $page, $size, $country);
        if ($payload === null) {
            return response()->json(['message' => 'Discovery no disponible o clau API absent'], 503);
        }

        return response()->json($payload);
    }

    public function importByExternalId(Request $request): JsonResponse
    {
        $data = $request->validate([
            'external_tm_id' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->ticketmasterEventImportService->importSingleFromDiscoveryByExternalId($data['external_tm_id']);

        if ($result['ok'] !== true) {
            $status = 422;
            if (isset($result['http_status']) && is_int($result['http_status'])) {
                $status = $result['http_status'];
            }

            return response()->json([
                'message' => $result['message'] ?? 'Importació no vàlida',
            ], $status);
        }

        return response()->json([
            'status' => 'imported',
            'event_id' => $result['event_id'],
            'inserted' => $result['inserted'],
        ], 201);
    }
}
