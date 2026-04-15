<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminLogResource;
use App\Models\AdminLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        if ($perPage < 1) {
            $perPage = 10;
        }
        if ($perPage > 50) {
            $perPage = 50;
        }

        $paginator = AdminLog::query()
            ->with('adminUser')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $items = [];
        $collection = $paginator->items();
        foreach ($collection as $row) {
            $items[] = (new AdminLogResource($row))->resolve();
        }

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
