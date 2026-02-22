<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;

class Authenticate
{
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function handle($request, Closure $next, $guard = null)
    {
        $token = $request->bearerToken();
        if (!$token) {
            $token = $request->header('api-token');
        }
        if (!$token) {
            $token = $request->input('api_token');
        }
        
        if (!$token) {
            return response()->json([
                'message' => 'Token not provided',
                'error' => 'Unauthorized'
            ], 401);
        }

        $user = \App\Models\User::where('api_token', $token)->first();
        
        if (!$user) {
            return response()->json([
                'message' => 'Invalid token',
                'error' => 'Unauthorized'
            ], 401);
        }

        $request->merge(['user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $this->auth->guard($guard)->setUser($user);
        
        return $next($request);
    }
}