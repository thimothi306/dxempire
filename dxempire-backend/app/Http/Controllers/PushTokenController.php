<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    use ApiResponse;

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'token'       => ['required', 'string', 'max:500'],
            'device_type' => ['nullable', 'in:android,ios'],
        ]);

        PushToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $request->token],
            ['device_type' => $request->input('device_type', 'android'), 'created_at' => now()]
        );

        return $this->success(null, 'Push token registered.');
    }

    public function unregister(Request $request): JsonResponse
    {
        $query = PushToken::where('user_id', $request->user()->id);

        if ($request->filled('token')) {
            $query->where('token', $request->token);
        }

        $query->delete();

        return $this->success(null, 'Push token removed.');
    }
}
