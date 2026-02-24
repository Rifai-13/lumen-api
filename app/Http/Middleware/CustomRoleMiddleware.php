<?php
// app/Http/Middleware/CustomRoleMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class CustomRoleMiddleware
{
    public function handle($request, Closure $next, ...$roles)
    {
        Log::info('========== CUSTOM ROLE MIDDLEWARE ==========');
        
        // Dapatkan user dari berbagai sumber
        $user = null;
        
        // 1. Dari Auth facade
        if (Auth::check()) {
            $user = Auth::user();
            Log::info('User dari Auth facade: ' . ($user ? $user->email : 'null'));
        }
        
        // 2. Dari request attribute
        if (!$user && $request->auth_user) {
            $user = $request->auth_user;
            Log::info('User dari request->auth_user: ' . $user->email);
            
            // Set ke Auth facade
            Auth::setUser($user);
        }
        
        // 3. Manual lookup dari token
        if (!$user) {
            $token = $request->bearerToken();
            if ($token) {
                $session = \App\Models\Session::with('user')
                    ->where('id', $token)
                    ->first();
                    
                if ($session && $session->user) {
                    $user = $session->user;
                    Log::info('User dari manual lookup: ' . $user->email);
                    
                    // Set ke Auth facade
                    Auth::setUser($user);
                    $request->merge(['auth_user' => $user]);
                }
            }
        }

        if (!$user) {
            Log::error('Tidak ada user ditemukan');
            return response()->json([
                'success' => false,
                'message' => 'User is not logged in.'
            ], 403);
        }

        // Cek role secara manual dari database
        $userRole = $this->getUserRole($user);
        Log::info('User role dari database: ' . $userRole);
        
        // Cek apakah role user sesuai dengan yang dibutuhkan
        foreach ($roles as $role) {
            if ($userRole === $role) {
                Log::info("User memiliki role: {$role}");
                return $next($request);
            }
        }

        Log::warning('User tidak memiliki role yang diperlukan');
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized - insufficient role'
        ], 403);
    }
    
    private function getUserRole($user)
    {
        // Coba dari relasi Spatie
        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames();
            if ($roles->isNotEmpty()) {
                return $roles->first();
            }
        }
        
        // Query manual ke database
        $role = \Illuminate\Support\Facades\DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->first();
            
        return $role ? $role->name : ($user->role ?? 'staff');
    }
}