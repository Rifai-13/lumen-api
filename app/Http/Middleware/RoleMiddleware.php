<?php
// app/Http/Middleware/RoleMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User is not logged in.'
            ], 401);
        }
        
        Log::info('========== ROLE MIDDLEWARE CHECK ==========');
        Log::info('User ID: ' . $user->id);
        Log::info('User Email: ' . $user->email);
        Log::info('User Roles: ' . json_encode($user->getRoleNames()));
        Log::info('Required Roles: ' . implode(', ', $roles));
        
        // Cek apakah user memiliki SALAH SATU dari role yang diperlukan
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                Log::info('✅ Access GRANTED for role: ' . $role);
                return $next($request);
            }
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Forbidden - You do not have the required role',
            'required_roles' => $roles,
            'your_roles' => $user->getRoleNames()
        ], 403);
    }
}