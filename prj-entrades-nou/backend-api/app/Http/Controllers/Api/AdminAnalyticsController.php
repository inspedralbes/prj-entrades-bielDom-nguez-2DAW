<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Analítiques agregades (admin): període global, REST.
 */
class AdminAnalyticsController extends Controller
{
    public function __construct (
        private readonly AdminAnalyticsService $analytics,
    ) {}

    public function summary (Request $request): JsonResponse
    {
        $range = $this->parseDateRange($request);
        if ($range instanceof JsonResponse) {
            return $range;
        }

        $payload = $this->analytics->buildSummary($range['from_start'], $range['to_end']);
        $payload['period'] = [
            'from' => Carbon::parse($range['date_from'])->toDateString(),
            'to' => Carbon::parse($range['date_to'])->toDateString(),
        ];

        return response()->json($payload);
    }

    public function events (Request $request): JsonResponse
    {
        $range = $this->parseDateRange($request);
        if ($range instanceof JsonResponse) {
            return $range;
        }

        $payload = $this->analytics->buildPerEvent(
            $range['from_start'],
            $range['to_end'],
            $range['days_in_period']
        );

        return response()->json($payload);
    }

    public function categoryOccupancy (Request $request): JsonResponse
    {
        $range = $this->parseDateRange($request);
        if ($range instanceof JsonResponse) {
            return $range;
        }

        $payload = $this->analytics->buildCategoryOccupancy($range['from_start'], $range['to_end']);

        return response()->json($payload);
    }

    /**
     * @return array{from_start: \Carbon\Carbon, to_end: \Carbon\Carbon, days_in_period: int}|\Illuminate\Http\JsonResponse
     */
    private function parseDateRange (Request $request): JsonResponse|array
    {
        $data = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
        ]);

        $fromStart = Carbon::parse($data['date_from'])->startOfDay();
        $toEnd = Carbon::parse($data['date_to'])->endOfDay();

        if ($fromStart->greaterThan($toEnd)) {
            return response()->json(['message' => 'El rang date_from / date_to no és vàlid'], 422);
        }

        $maxDays = 93;
        if ($fromStart->diffInDays($toEnd) > $maxDays) {
            return response()->json(['message' => 'Rang massa ampli (màx. '.$maxDays.' dies)'], 422);
        }

        $fromDay = Carbon::parse($data['date_from'])->startOfDay();
        $toDay = Carbon::parse($data['date_to'])->startOfDay();
        $daysInPeriod = (int) $fromDay->diffInDays($toDay) + 1;
        if ($daysInPeriod < 1) {
            $daysInPeriod = 1;
        }

        return [
            'from_start' => $fromStart,
            'to_end' => $toEnd,
            'days_in_period' => $daysInPeriod,
            'date_from' => $data['date_from'],
            'date_to' => $data['date_to'],
        ];
    }
}
