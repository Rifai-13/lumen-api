<?php
// app/Http/Middleware/AuthenticateWithSession.php

namespace App\Http\Middleware;

use Closure;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithSession
{
    public function handle($request, Closure $next)
    {
        // Ambil token dari header
        $token = $request->bearerToken();
        
        Log::info('🔐 Auth Middleware - Start');
        Log::info('Token exists: ' . ($token ? 'YES' : 'NO'));
        
        if (!$token) {
            Log::warning('No token provided');
            return response()->json([
                'success' => false,
                'message' => 'No token provided'
            ], 401);
        }

        // Cari session di database
        $session = Session::with('user')
            ->where('id', $token)
            ->first();

        if (!$session) {
            Log::warning('Session not found for token: ' . substr($token, 0, 20) . '...');
            return response()->json([
                'success' => false,
                'message' => 'Invalid session'
            ], 401);
        }

        if (Carbon::parse($session->expires_at)->isPast()) {
            Log::warning('Session expired for user: ' . $session->user_id);
            $session->delete();
            return response()->json([
                'success' => false,
                'message' => 'Session expired'
            ], 401);
        }

        if (!$session->user) {
            Log::warning('User not found for session');
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 401);
        }

        $user = $session->user;
        
        Log::info('User found:', [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role ?? 'unknown'
        ]);

        // Update last activity
        $session->update(['last_activity' => time()]);

        // ============ PENTING! ============
        // 1. Attach user ke request
        $request->merge(['auth_user' => $user]);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // 2. Login user ke Auth facade (PENTING UNTUK SPATIE)
        Auth::setUser($user);
        
        // 3. Login menggunakan guard (alternatif)
        Auth::guard('api')->setUser($user);

        Log::info('✅ User set in Auth facade: ' . Auth::check() ? 'YES' : 'NO');
        Log::info('🔐 Auth Middleware - End');

        return $next($request);
    }
}