<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class UserIsBlocked
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorizationHeader = $request->header('Authorization');
        if ($authorizationHeader) {
            $user = Auth::user();
            if($user->delete_reason){
                return response()->json([
                    'status' => 'blocked',
                    'message' => 'User blocked',
                    'reason' => $user->delete_reason
                ], 403);
            }
        }
        return $next($request);
    }
}
