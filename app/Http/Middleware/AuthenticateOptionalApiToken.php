<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOptionalApiToken
{
    /**
     * Guest requests continue. A bearer token resolves the user on the Sanctum
     * guard so feed fields and qualified views match a logged-in website visit.
     * An invalid token is rejected instead of being treated as a guest.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! filled($request->bearerToken())) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $user->tokenCan('mobile')) {
            return response()->json([
                'message' => 'Invalid ability provided.',
            ], 403);
        }

        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
