<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->profileDisplayName(),
            'username' => $user->username,
            'email' => $user->email,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $usernameRaw = $request->input('username');
        if (is_string($usernameRaw)) {
            $request->merge(['username' => trim($usernameRaw)]);
        }

        $rules = [
            'username' => ['sometimes', 'string', 'min:3', 'max:255', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ];

        $passwordValue = $request->input('password');
        $wantsPasswordChange = is_string($passwordValue) && $passwordValue !== '';
        if ($wantsPasswordChange) {
            $rules['current_password'] = ['required', 'string'];
            $rules['password'] = ['required', 'string', 'confirmed', Password::min(8)];
        }

        $data = $request->validate($rules);

        if (isset($data['username'])) {
            $user->username = trim((string) $data['username']);
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if ($wantsPasswordChange) {
            $current = $request->input('current_password');
            if (! is_string($current) || ! Hash::check($current, $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['La contrasenya actual no és correcta.'],
                ]);
            }
            $user->password = $passwordValue;
        }

        $user->save();

        return $this->show($request);
    }
}
