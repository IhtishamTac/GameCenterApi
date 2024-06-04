<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RequestBodyToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Retrieve the token from the request body
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Find the token
        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Authenticate the user
        Auth::attempt($accessToken->token);

        return $next($request);
    }
}
