<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        // Ambil user dari request (set oleh Authenticate middleware)
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'User is not logged in.',
                'error' => 'Unauthorized'
            ], 401);
        }
        
        Log::info('Role check:', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_roles' => $user->getRoleNames()->toArray(),
            'required_role' => $role,
            'has_role' => $user->hasRole($role)
        ]);
        
        // Cek apakah user memiliki role yang diminta
        if (!$user->hasRole($role)) {
            return response()->json([
                'message' => 'Forbidden - You do not have the required role',
                'required' => $role,
                'your_roles' => $user->getRoleNames(),
                'user_id' => $user->id
            ], 403);
        }
        
        return $next($request);
    }
}