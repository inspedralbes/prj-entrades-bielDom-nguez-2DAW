<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TicketTransfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Gestió d’usuaris (rol admin).
 */
class AdminUsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::query()->with('roles');

        $search = $request->query('q');
        if (is_string($search) && trim($search) !== '') {
            $term = '%'.addcslashes(trim($search), '%_\\').'%';
            $q->where(static function ($qq) use ($term) {
                $qq->whereRaw('LOWER(name) LIKE LOWER(?)', [$term])
                    ->orWhereRaw('LOWER(email) LIKE LOWER(?)', [$term]);
            });
        }

        $perPage = (int) $request->query('per_page', 25);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 25;
        }

        $paginator = $q->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', 'max:50'],
        ]);

        $username = isset($data['username']) ? trim((string) $data['username']) : '';
        if ($username === '') {
            $username = 'u_'.substr(sha1($data['email'].microtime(true)), 0, 12);
        }

        $user = new User;
        $user->name = $data['name'];
        $user->username = $username;
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->save();

        if (isset($data['roles']) && is_array($data['roles'])) {
            $roles = $data['roles'];
            $n = count($roles);
            for ($i = 0; $i < $n; $i++) {
                $rn = $roles[$i];
                if (!is_string($rn)) {
                    continue;
                }
                if ($rn === '') {
                    continue;
                }
                $user->assignRole($rn);
            }
        } else {
            $user->assignRole('user');
        }

        $user->load('roles');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'roles' => $this->roleNamesForUser($user),
        ], 201);
    }

    public function destroy(Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        if ((int) $actor->id === $userId) {
            return response()->json(['message' => 'No pots eliminar el teu propi usuari des del panell.'], 403);
        }

        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['message' => 'Usuari no trobat'], 404);
        }

        if ($user->hasRole('admin')) {
            $adminCount = User::query()->role('admin')->count();
            if ($adminCount <= 1) {
                return response()->json(['message' => 'No es pot eliminar l’últim administrador.'], 422);
            }
        }

        $user->delete();

        return response()->json(['message' => 'Usuari eliminat']);
    }

    public function orders(Request $request, int $userId): JsonResponse
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['message' => 'Usuari no trobat'], 404);
        }

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with(['event', 'orderLines.ticket', 'orderLines.seat'])
            ->orderByDesc('updated_at')
            ->get();

        $outOrders = [];
        $oi = 0;
        for (; $oi < count($orders); $oi++) {
            $order = $orders[$oi];
            $linesOut = [];
            $orderLines = $order->orderLines;
            $li = 0;
            for (; $li < count($orderLines); $li++) {
                $line = $orderLines[$li];
                $ticket = $line->ticket;
                $validated = false;
                if ($ticket !== null && $ticket->used_at !== null) {
                    $validated = true;
                }
                $transferOut = null;
                if ($ticket !== null) {
                    $transferOut = $this->lastTransferForTicket((string) $ticket->id);
                }
                $lineRow = [
                    'order_line_id' => $line->id,
                    'seat_id' => $line->seat_id,
                    'unit_price' => (string) $line->unit_price,
                    'ticket' => null,
                ];
                if ($ticket !== null) {
                    $lineRow['ticket'] = [
                        'id' => $ticket->id,
                        'status' => $ticket->status,
                        'validated' => $validated,
                        'used_at' => $ticket->used_at?->toIso8601String(),
                        'transfer' => $transferOut,
                    ];
                }
                $linesOut[] = $lineRow;
            }
            $ev = $order->event;
            $eventPayload = null;
            if ($ev !== null) {
                $eventPayload = [
                    'id' => $ev->id,
                    'name' => $ev->name,
                    'starts_at' => $ev->starts_at?->toIso8601String(),
                ];
            }
            $outOrders[] = [
                'id' => $order->id,
                'state' => $order->state,
                'total_amount' => (string) $order->total_amount,
                'currency' => $order->currency,
                'updated_at' => $order->updated_at?->toIso8601String(),
                'event' => $eventPayload,
                'lines' => $linesOut,
            ];
        }

        return response()->json([
            'user_id' => $user->id,
            'orders' => $outOrders,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function roleNamesForUser(User $user): array
    {
        $out = [];
        $roles = $user->roles;
        $i = 0;
        for (; $i < count($roles); $i++) {
            $out[] = $roles[$i]->name;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastTransferForTicket(string $ticketId): ?array
    {
        $tr = TicketTransfer::query()
            ->where('ticket_id', $ticketId)
            ->orderByDesc('created_at')
            ->first();
        if ($tr === null) {
            return null;
        }

        return [
            'from_user_id' => $tr->from_user_id,
            'to_user_id' => $tr->to_user_id,
            'status' => $tr->status,
            'created_at' => $tr->created_at?->toIso8601String(),
        ];
    }
}
