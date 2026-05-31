<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Authenticate an admin and issue a Sanctum personal access token.
     *
     * Returns 401 with a uniform `{error:"Invalid email or password"}` envelope
     * for both wrong-password and unknown-email cases (AUTH-01, ADR-3).
     * The `error` key matches the frontend API contract error envelope.
     * Validation errors (missing fields) follow Laravel's default 422 shape.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['error' => 'Invalid email or password'], 401);
        }

        $token = $user->createToken('admin')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => (new UserResource($user))->toArray($request),
        ], 200);
    }

    /**
     * Return the authenticated admin (UserResource).
     */
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user);
    }

    /**
     * Revoke the current Sanctum access token (204 No Content).
     */
    public function logout(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
