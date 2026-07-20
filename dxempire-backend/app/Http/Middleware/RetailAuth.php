<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RetailAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        $customerId = Cache::get('retail_token_' . $token);

        if (!$customerId) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token.'], 401);
        }

        $customer = Customer::find($customerId);

        if (!$customer || !$customer->is_active) {
            return response()->json(['success' => false, 'message' => 'Account not found or deactivated.'], 401);
        }

        $request->merge(['customer' => $customer]);
        $request->setUserResolver(fn() => $customer);

        return $next($request);
    }
}
