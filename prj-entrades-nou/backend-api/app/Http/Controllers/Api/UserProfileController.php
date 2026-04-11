<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserProfileController extends Controller
{
    public function show (Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = UserSetting::query()->find($user->id);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'gemini_personalization_enabled' => $settings?->gemini_personalization_enabled ?? true,
        ]);
    }

    public function updateProfile (Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,'.$user->id],
        ]);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (isset($data['username'])) {
            $user->username = $data['username'];
        }
        $user->save();

        return $this->show($request);
    }

    public function updateSettings (Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'gemini_personalization_enabled' => ['required', 'boolean'],
        ]);

        UserSetting::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['gemini_personalization_enabled' => $data['gemini_personalization_enabled']],
        );

        return $this->show($request);
    }
}
