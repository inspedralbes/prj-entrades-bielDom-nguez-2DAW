<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Autocompletat Google Places (Espanya) per a la cerca d’ubicacions al client.
 */
class PlacesController extends Controller
{
    /**
     * Prediccions d’adreces i localitats a Espanya (components=country:es).
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $raw = $request->query('q');
        if ($raw === null) {
            return response()->json(['places' => []]);
        }
        $trim = trim((string) $raw);
        if (strlen($trim) < 2) {
            return response()->json(['places' => []]);
        }

        $key = config('services.google.maps_api_key');
        if ($key === null || $key === '') {
            return response()->json(['places' => []]);
        }

        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input' => $trim,
            'key' => $key,
            'components' => 'country:es',
            'language' => 'ca',
        ]);

        if (! $response->successful()) {
            return response()->json(['places' => []]);
        }

        $data = $response->json();
        $status = '';
        if (isset($data['status'])) {
            $status = (string) $data['status'];
        }
        if ($status !== 'OK' && $status !== 'ZERO_RESULTS') {
            return response()->json(['places' => []]);
        }

        $predictions = [];
        if (isset($data['predictions']) && is_array($data['predictions'])) {
            $predictions = $data['predictions'];
        }

        $out = [];
        $max = 8;
        $n = 0;
        $predCount = count($predictions);
        for ($i = 0; $i < $predCount && $n < $max; $i++) {
            $p = $predictions[$i];
            if (! is_array($p)) {
                continue;
            }
            $pid = '';
            if (isset($p['place_id'])) {
                $pid = (string) $p['place_id'];
            }
            $desc = '';
            if (isset($p['description'])) {
                $desc = (string) $p['description'];
            }
            if ($pid === '' || $desc === '') {
                continue;
            }
            $out[] = [
                'place_id' => $pid,
                'label' => $desc,
            ];
            $n++;
        }

        return response()->json(['places' => $out]);
    }

    /**
     * Coordenades i nom per aplicar filtre de mapa / proximitat després d’elegir una predicció.
     */
    public function details(Request $request): JsonResponse
    {
        $placeId = $request->query('place_id');
        if ($placeId === null || trim((string) $placeId) === '') {
            return response()->json(['message' => 'place_id requerit'], 422);
        }

        $key = config('services.google.maps_api_key');
        if ($key === null || $key === '') {
            return response()->json(['message' => 'Places no configurat'], 503);
        }

        $response = Http::timeout(10)->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => (string) $placeId,
            'key' => $key,
            'fields' => 'geometry,formatted_address,name',
            'language' => 'ca',
        ]);

        if (! $response->successful()) {
            return response()->json(['message' => 'Error Places'], 502);
        }

        $data = $response->json();
        $status = '';
        if (isset($data['status'])) {
            $status = (string) $data['status'];
        }
        if ($status !== 'OK') {
            return response()->json(['message' => 'Lloc no trobat'], 404);
        }

        $result = null;
        if (isset($data['result']) && is_array($data['result'])) {
            $result = $data['result'];
        }
        if ($result === null) {
            return response()->json(['message' => 'Resposta invàlida'], 502);
        }

        $lat = null;
        $lng = null;
        if (isset($result['geometry']['location']['lat'])) {
            $lat = (float) $result['geometry']['location']['lat'];
        }
        if (isset($result['geometry']['location']['lng'])) {
            $lng = (float) $result['geometry']['location']['lng'];
        }

        $name = '';
        if (isset($result['name'])) {
            $name = (string) $result['name'];
        }
        $formatted = '';
        if (isset($result['formatted_address'])) {
            $formatted = (string) $result['formatted_address'];
        }

        return response()->json([
            'lat' => $lat,
            'lng' => $lng,
            'name' => $name,
            'formatted_address' => $formatted,
        ]);
    }
}
