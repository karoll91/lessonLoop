<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('login', $request->string('login'))->first();

        if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['login' => 'Login yoki parol noto\'g\'ri.']);
        }

        if ($user->isBlocked()) {
            throw ValidationException::withMessages(['login' => 'Akkaunt bloklangan. Admin bilan bog\'laning.']);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $user->createToken($request->input('device_name') ?? 'mobile')->plainTextToken,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /**
     * Change the own password; required after a temporary password.
     * Other devices are signed out, the current token stays valid.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['password' => $request->string('password'), 'must_change_password' => false]);
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * @return array{id: int, name: string, login: string, role: string, must_change_password: bool}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'login' => $user->login,
            'role' => $user->role->value,
            'must_change_password' => $user->must_change_password,
        ];
    }
}
