<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower($request->input('email'))]);
        }
        $credentials = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', 'unique:users'],
            'password' => ['required', 'string', 'max:72', Password::min(8), $this->limitPasswordBytes(...)],
        ]);

        $user = User::createOrFirst(['email' => $credentials['email']], $credentials);
        abort_unless($user->wasRecentlyCreated, 422, 'This email address is already registered.');

        return $this->tokenResponse($user, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:72', $this->limitPasswordBytes(...)],
        ]);

        $user = User::where('email', mb_strtolower($credentials['email']))->first();
        $hash = $user?->password ?? config('auth.dummy_password_hash');
        abort_unless(Hash::check($credentials['password'], $hash) && $user, 401, 'Invalid credentials.');

        return $this->tokenResponse($user, 200);
    }

    public function logout(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    private function limitPasswordBytes(string $attribute, mixed $value, \Closure $fail): void
    {
        if (is_string($value) && strlen($value) > 72) {
            $fail('The password must not exceed 72 bytes.');
        }
    }

    private function tokenResponse(User $user, int $status): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => $user->only('id', 'name', 'email'),
                'token' => $user->createToken('ride-booking', ['*'], now()->addDay())->plainTextToken,
            ],
        ], $status)->header('Cache-Control', 'no-store');
    }
}
