<?php
// app/Http/Middleware/RoleMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        Log::info('========== ROLE MIDDLEWARE ==========');
        Log::info('Path: ' . $request->path());
        Log::info('Roles required: ' . implode(', ', $roles));
        
        // 1. Coba dapatkan user dari Auth facade
        $user = Auth::user();
        Log::info('User from Auth::user(): ' . ($user ? 'YES' : 'NO'));
        
        // 2. Jika tidak ada, coba dari request
        if (!$user) {
            $user = $request->auth_user;
            Log::info('User from request->auth_user: ' . ($user ? 'YES' : 'NO'));
        }
        
        // 3. Jika masih tidak ada, coba dari bearer token manual
        if (!$user) {
            $token = $request->bearerToken();
            Log::info('Trying manual lookup with token: ' . ($token ? 'YES' : 'NO'));
            
            if ($token) {
                $session = \App\Models\Session::with('user')
                    ->where('id', $token)
                    ->first();
                    
                if ($session && $session->user) {
                    $user = $session->user;
                    Log::info('User found via manual lookup: ' . $user->email);
                    
                    // Set ke Auth facade
                    Auth::setUser($user);
                    $request->merge(['auth_user' => $user]);
                }
            }
        }

        if (!$user) {
            Log::error('❌ No user found in role middleware');
            return response()->json([
                'success' => false,
                'message' => 'User is not logged in.'
            ], 403);
        }

        Log::info('User ID: ' . $user->id);
        Log::info('User Email: ' . $user->email);
        Log::info('User roles: ' . json_encode($user->getRoleNames()));

        // Cek role menggunakan Spatie
        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                Log::info("✅ User has role: {$role}");
                return $next($request);
            }
        }

        Log::warning('❌ User does not have required roles');
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized - insufficient role'
        ], 403);
    }
}