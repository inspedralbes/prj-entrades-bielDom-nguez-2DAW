<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\AdminUserDirectoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

/**
 * Gestió d’usuaris (rol admin).
 */
class AdminUsersController extends Controller
{
    public function __construct(
        private readonly AdminUserDirectoryService $adminUserDirectory,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->adminUserDirectory->paginatedUsers($request));
    }

    public function store(Request $request): JsonResponse
    {
        $body = $this->adminUserDirectory->createUser($request);

        return response()->json($body, 201);
    }

    public function update(Request $request, int $userId): JsonResponse
    {
        $result = $this->adminUserDirectory->updateUser($request, $userId);
        if ($result['ok'] === false) {
            return response()->json($result['body'], $result['status']);
        }

        return response()->json($result['body']);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $result = $this->adminUserDirectory->deleteUser($actor, $userId);
        if ($result['ok'] === false) {
            return response()->json($result['body'], $result['status']);
        }

        return response()->json($result['body']);
    }

    public function orders(Request $request, int $userId): JsonResponse
    {
        $result = $this->adminUserDirectory->ordersPayloadForUser($userId);
        if ($result['ok'] === false) {
            return response()->json($result['body'], $result['status']);
        }

        return response()->json($result['body']);
    }
}
