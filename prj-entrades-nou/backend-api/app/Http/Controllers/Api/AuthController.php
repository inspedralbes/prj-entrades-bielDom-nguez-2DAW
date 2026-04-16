<?php

namespace App\Http\Controllers\Api;

//================================ NAMESPACES / IMPORTS ============

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

//================================ PROPIETATS / ATRIBUTS ==========

//================================ MÈTODES / FUNCIONS ===========

class AuthController extends Controller
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenService
    ) {}

    public function register(Request $request): JsonResponse
    {
        $this->trimRegistrationStrings($request);

        // Unicitat de correu insensible a majúscules (coherent amb el login per LOWER(email)).
        $emailUniqueRule = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value)) {
                return;
            }
            $needle = strtolower(trim($value));
            if ($needle === '') {
                return;
            }
            $exists = User::query()->whereRaw('LOWER(email) = ?', [$needle])->exists();
            if ($exists) {
                $fail('(Aquest correu ja ha estat registrat.)');
            }
        };

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email', 'max:255', $emailUniqueRule],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'username.required' => '(El nom d’usuari és obligatori.)',
            'username.min' => '(El nom d’usuari ha de tenir com a mínim :min caràcters.)',
            'username.max' => '(El nom d’usuari és massa llarg.)',
            'username.regex' => '(El nom d’usuari només pot contenir lletres, números, guions i guions baixos.)',
            'username.unique' => '(Aquest nom d’usuari ja ha estat registrat.)',
            'email.required' => '(El correu electrònic és obligatori.)',
            'email.email' => '(El correu electrònic no és vàlid.)',
            'password.required' => '(La contrasenya és obligatòria.)',
            'password.min' => '(La contrasenya ha de tenir com a mínim :min caràcters.)',
            'password.confirmed' => '(Les contrasenyes no coincideixen.)',
        ]);

        $usernameTrim = trim((string) $validated['username']);

        $user = User::query()->create([
            'name' => null,
            'username' => $usernameTrim,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        $user->assignRole('user');

        $token = $this->jwtTokenService->issueForUser($user->fresh());

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userProfile($user),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $emailRaw = $request->input('email');
        if (is_string($emailRaw)) {
            $request->merge(['email' => strtolower(trim($emailRaw))]);
        }

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Cerca insensible a majúscules (Postgres compara VARCHAR de forma sensible per defecte).
        $user = User::query()->whereRaw('LOWER(email) = ?', [$validated['email']])->first();

        $hashFromDb = '';
        if ($user !== null) {
            $hashFromDb = $user->getRawOriginal('password');
        }
        if ($user === null || $hashFromDb === '' || ! Hash::check($validated['password'], $hashFromDb)) {
            throw ValidationException::withMessages([
                'email' => ['Credencials incorrectes.'],
            ]);
        }

        $token = $this->jwtTokenService->issueForUser($user);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userProfile($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['message' => 'No autenticat'], 401);
        }

        return response()->json($this->userProfile($user));
    }

    //================================ LÒGICA PRIVADA ================

    /**
     * @return array<string, mixed>
     */
    private function userProfile(User $user): array
    {
        $roles = [];
        foreach ($user->getRoleNames() as $r) {
            $roles[] = (string) $r;
        }

        return [
            'id' => $user->id,
            'name' => $user->profileDisplayName(),
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $roles,
        ];
    }

    /**
     * Normalitza correu i nom d’usuari abans de validar (sense alterar les contrasenyes).
     */
    private function trimRegistrationStrings(Request $request): void
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => strtolower(trim($email))]);
        }
        $password = $request->input('password');
        if (is_int($password) || is_float($password)) {
            $request->merge(['password' => (string) $password]);
        }
        $passwordConfirmation = $request->input('password_confirmation');
        if (is_int($passwordConfirmation) || is_float($passwordConfirmation)) {
            $request->merge(['password_confirmation' => (string) $passwordConfirmation]);
        }
        $username = $request->input('username');
        if (is_string($username)) {
            $request->merge(['username' => trim($username)]);
        }
    }
}
