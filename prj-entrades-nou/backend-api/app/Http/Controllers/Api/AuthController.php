<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\JwtTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly JwtTokenService $jwtTokenService
    )
    {
    }

    public function register(Request $request): JsonResponse
    {
        $this->trimRegistrationStrings($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'El nom és obligatori.',
            'email.required' => 'El correu electrònic és obligatori.',
            'email.email' => 'El correu electrònic no és vàlid.',
            'email.unique' => 'Aquest correu ja està registrat.',
            'password.required' => 'La contrasenya és obligatòria.',
            'password.min' => 'La contrasenya ha de tenir com a mínim :min caràcters.',
            'password.confirmed' => 'La confirmació de la contrasenya no coincideix.',
            'username.unique' => 'Aquest nom d’usuari ja està en ús.',
        ]);

        $usernameInput = $validated['username'] ?? '';
        if (!is_string($usernameInput)) {
            $usernameInput = '';
        }
        $usernameTrim = trim($usernameInput);
        if ($usernameTrim === '') {
            $usernameTrim = $this->generateUniqueUsername($validated['name']);
        }

        $user = User::query()->create([
            'name' => $validated['name'],
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

        $hashFromDb = $user !== null ? $user->getRawOriginal('password') : '';
        if ($user === null || $hashFromDb === '' || !Hash::check($validated['password'], $hashFromDb)) {
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
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'roles' => $roles,
        ];
    }

    /**
     * Treu espais accidentals al correu/nom/contrasenyes abans de validar (evita correu «invàlid» o confirmació desalineada).
     */
    private function trimRegistrationStrings(Request $request): void
    {
        $email = $request->input('email');
        if (is_string($email)) {
            $request->merge(['email' => trim($email)]);
        }
        $name = $request->input('name');
        if (is_string($name)) {
            $request->merge(['name' => trim($name)]);
        }
        // No fer trim de contrasenyes: pot alterar caràcters vàlids; el client ja envia el mateix en ambdós camps.
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

    /**
     * Nom d’usuari intern (BD) quan el registre només envia el nom visible.
     */
    private function generateUniqueUsername(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'usuari';
        }
        if (strlen($base) > 200) {
            $base = substr($base, 0, 200);
        }
        $suffix = '';
        $n = 0;
        while (true) {
            $candidate = $base.$suffix;
            if (strlen($candidate) > 255) {
                $candidate = substr($candidate, 0, 255);
            }
            if (!User::query()->where('username', $candidate)->exists()) {
                return $candidate;
            }
            $n = $n + 1;
            $suffix = '_'.(string) $n;
        }
    }
}
