<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TicketTransfer;
use App\Models\User;
use App\Services\Admin\AdminAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Gestió d’usuaris (rol admin).
 */
class AdminUsersController extends Controller
{
    public function __construct (
        private readonly AdminAuditLogService $adminAuditLog,
    ) {}

    public function index (Request $request): JsonResponse
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

    public function store (Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', 'in:user,admin'],
        ]);

        $username = isset($data['username']) ? trim((string) $data['username']) : '';
        if ($username === '') {
            $username = 'u_'.substr(sha1($data['email'].microtime(true)), 0, 12);
        }

        $user = new User();
        $user->name = $data['name'];
        $user->username = $username;
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->save();

        $user->syncRoles([$data['role']]);

        $user->load('roles');

        $actor = $request->user();
        if ($actor !== null) {
            $this->adminAuditLog->record(
                (int) $actor->id,
                'user_created',
                'User',
                (int) $user->id,
                'S\'ha creat l\'usuari «'.$user->name.'» ('.$user->email.'). Rol: '.$data['role'].'.',
                $request->ip()
            );
        }

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $data['role'],
            'roles' => $this->roleNamesForUser($user),
        ], 201);
    }

    public function update (Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['message' => 'Usuari no trobat'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'in:user,admin'],
        ]);

        if (count($data) === 0) {
            return response()->json(['message' => 'Envia almenys un camp editable.'], 422);
        }

        if (array_key_exists('role', $data)) {
            $newRole = $data['role'];
            $willHaveAdmin = $newRole === 'admin';
            if ($user->hasRole('admin') && ! $willHaveAdmin) {
                $adminCount = User::query()->role('admin')->count();
                if ($adminCount <= 1) {
                    return response()->json(['message' => 'No es pot treure el rol admin a l’últim administrador.'], 422);
                }
            }
            $user->syncRoles([$newRole]);
        }

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }
        if (array_key_exists('username', $data)) {
            $un = $data['username'];
            if ($un === null || (is_string($un) && trim($un) === '')) {
                $user->username = 'u_'.substr(sha1($user->email.(string) microtime(true)), 0, 12);
            } else {
                $user->username = trim((string) $un);
            }
        }
        if (array_key_exists('password', $data)) {
            $pw = $data['password'];
            if (is_string($pw) && trim($pw) !== '') {
                $user->password = $pw;
            }
        }

        $user->save();
        $user->load('roles');

        $summaryParts = [];
        if (array_key_exists('name', $data)) {
            $summaryParts[] = 'nom';
        }
        if (array_key_exists('email', $data)) {
            $summaryParts[] = 'email';
        }
        if (array_key_exists('username', $data)) {
            $summaryParts[] = 'usuari';
        }
        if (array_key_exists('password', $data)) {
            $pw = $data['password'];
            if (is_string($pw) && trim($pw) !== '') {
                $summaryParts[] = 'contrasenya';
            }
        }
        if (array_key_exists('role', $data)) {
            $summaryParts[] = 'rol';
        }
        $summaryJoin = '';
        $spi = 0;
        for (; $spi < count($summaryParts); $spi++) {
            if ($spi > 0) {
                $summaryJoin = $summaryJoin.', ';
            }
            $summaryJoin = $summaryJoin.$summaryParts[$spi];
        }
        if ($summaryJoin === '') {
            $summaryJoin = 'dades';
        }

        $this->adminAuditLog->record(
            (int) $actor->id,
            'user_updated',
            'User',
            (int) $user->id,
            'S\'ha actualitzat l\'usuari «'.$user->name.'» (id '.$user->id.'): '.$summaryJoin.'.',
            $request->ip()
        );

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username,
            'role' => $this->primaryRoleName($user),
            'roles' => $this->roleNamesForUser($user),
        ]);
    }

    public function destroy (Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
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

        $deletedId = (int) $user->id;
        $deletedName = (string) $user->name;
        $deletedEmail = (string) $user->email;

        $user->delete();

        $this->adminAuditLog->record(
            (int) $actor->id,
            'user_deleted',
            'User',
            $deletedId,
            'S\'ha eliminat l\'usuari id '.$deletedId.' («'.$deletedName.'», '.$deletedEmail.').',
            $request->ip()
        );

        return response()->json(['message' => 'Usuari eliminat']);
    }

    public function orders (Request $request, int $userId): JsonResponse
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
     * Darreres comandes (panell usuaris): mostra flux recents sense filtrar per usuari.
     */
    public function recentOrders (Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 8);
        if ($limit < 1) {
            $limit = 8;
        }
        if ($limit > 24) {
            $limit = 24;
        }

        $orders = Order::query()
            ->with(['event', 'user'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $rows = [];
        foreach ($orders as $order) {
            $eventName = '';
            if ($order->event !== null) {
                $eventName = (string) $order->event->name;
            }
            $buyerName = '—';
            if ($order->user !== null) {
                $buyerName = (string) $order->user->name;
            }
            $rows[] = [
                'id' => $order->id,
                'state' => $order->state,
                'total_amount' => (string) $order->total_amount,
                'currency' => $order->currency,
                'updated_at' => $order->updated_at?->toIso8601String(),
                'event_name' => $eventName,
                'buyer_name' => $buyerName,
            ];
        }

        return response()->json([
            'orders' => $rows,
        ]);
    }

    /**
     * Un sol rol per usuari (admin o user, preferència admin si coexistissin dades antigues).
     */
    private function primaryRoleName (User $user): string
    {
        if ($user->hasRole('admin')) {
            return 'admin';
        }
        $names = $this->roleNamesForUser($user);
        if (count($names) > 0) {
            return $names[0];
        }

        return 'user';
    }

    /**
     * @return array<int, string>
     */
    private function roleNamesForUser (User $user): array
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
    private function lastTransferForTicket (string $ticketId): ?array
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
