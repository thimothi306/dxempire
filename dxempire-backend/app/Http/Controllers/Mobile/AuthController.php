<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Login with Sales ID — password is required only if the account has
     * one set. Most sales-hierarchy accounts have no password (code-only
     * login, unchanged); anyone who's set one via Change Password must now
     * supply it too, matching whatever their account currently requires.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'unique_code' => 'required|string',
            'password'    => 'nullable|string',
        ]);

        $user = User::where('unique_code', $request->unique_code)
            ->where('is_active', true)
            ->first();

        if (!$user) {
            return $this->error('Invalid Sales ID or account is inactive', 401);
        }

        if ($user->password && (!$request->filled('password') || !Hash::check($request->password, $user->password))) {
            return $this->error('Invalid Sales ID or password', 401);
        }

        // Generate token
        $token = $user->createToken('mobile-app', ['role:' . $user->roles->first()?->name])->plainTextToken;

        return $this->success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'unique_code' => $user->unique_code,
                'role' => $user->roles->first()?->name,
            ],
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Get current user profile with hierarchy info
     */
    public function me(Request $request): JsonResponse
    {
        $user = auth()->user();

        return $this->success([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'unique_code' => $user->unique_code,
            'role' => $user->roles->first()?->name,
            'parent' => $user->parent ? [
                'id' => $user->parent->id,
                'name' => $user->parent->name,
                'unique_code' => $user->parent->unique_code,
                'role' => $user->parent->roles->first()?->name,
            ] : null,
            'department' => $user->department ?? null,
        ]);
    }

    /**
     * Logout (revoke token)
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully');
    }
}
