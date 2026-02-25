<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Login user and create session
     */

    public function login(Request $request)
    {
        try {
            $this->validate($request, [
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // HAPUS SEMUA SESSION LAMA (opsional)
            Session::where('user_id', $user->id)->delete();

            // Generate token yang BENAR (bukan user agent)
            $sessionToken = Str::random(60); // Contoh: "kR9s2jFpL7qW4nX8vB3mZ6cH1tY5uA0eD8gF2jK5"

            // PASTIKAN token unik
            while (Session::where('id', $sessionToken)->exists()) {
                $sessionToken = Str::random(60);
            }

            // Set masa berlaku 30 hari
            $expiresAt = Carbon::now()->addDays(30);

            // Buat session BARU - PASTIKAN 'id' TERISI DENGAN TOKEN!
            $session = Session::create([
                'id' => $sessionToken, // <- INI HARUS TOKEN RANDOM, BUKAN USER AGENT!
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(), // Ini untuk kolom user_agent
                'payload' => json_encode([
                    'created_at' => Carbon::now()->toDateTimeString(),
                    'user_agent' => $request->userAgent()
                ]),
                'last_activity' => time(),
                'expires_at' => $expiresAt
            ]);

            Log::info('Session created:', [
                'session_id' => $sessionToken,
                'session_id_prefix' => substr($sessionToken, 0, 10) . '...',
                'user_id' => $user->id,
                'expires_at' => $expiresAt
            ]);

            // Get user role
            $userRole = $this->getUserRole($user);
            $permissions = $this->getPermissionsForRole($userRole);

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $sessionToken, // Kembalikan token ke frontend
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $userRole,
                        'permissions' => $permissions,
                        'avatar' => $user->avatar ?? null
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        try {
            $user = $request->auth_user;

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Ambil permissions LANGSUNG dari database
            $permissions = $user->getAllPermissions()->pluck('name')->toArray();

            // Log untuk debugging
            Log::info('Me endpoint - User: ' . $user->email);
            Log::info('Permissions from database:', $permissions);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()->name ?? 'staff',
                    'permissions' => $permissions, // PASTIKAN INI TERKIRIM
                    'avatar' => $user->avatar ?? null
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Me error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user data'
            ], 500);
        }
    }
    /**
     * Get permissions based on role
     */
    private function getPermissionsForRole($role)
    {
        $permissions = [
            'admin' => [
                'view',
                'create',
                'edit',
                'delete',
                'view_campaigns',
                'create_campaigns',
                'edit_campaigns',
                'delete_campaigns',
                'view_users',
                'create_users',
                'edit_users',
                'delete_users',
                'manage_users',
                'view_stats',
                'export_data',
                'manage_settings',
                // Format dengan spasi juga
                'view campaigns',
                'create campaigns',
                'edit campaigns',
                'delete campaigns',
                'view users',
                'create users',
                'edit users',
                'delete users',
                'manage users',
            ],
            'manager' => [
                'view',
                'create',
                'edit',
                'delete',
                'view_campaigns',
                'view_stats',
                'export_data',
            ],
            'staff' => [
                'view',
                'view_campaigns',
            ],
        ];

        Log::info('Getting permissions for role: ' . $role, [
            'permissions' => $permissions[strtolower($role)] ?? $permissions['staff']
        ]);

        return $permissions[strtolower($role)] ?? $permissions['staff'];
    }

    /**
     * Logout user - delete session
     */
    public function logout(Request $request)
    {
        try {
            $token = str_replace('Bearer ', '', $request->header('Authorization'));

            if ($token) {
                Session::where('id', $token)->delete();
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Get user role from relationship
     */
    private function getUserRole($user)
    {
        // Coba dari relasi roles (Spatie)
        if (method_exists($user, 'roles') && $user->roles->count() > 0) {
            return $user->roles->first()->name;
        }

        // Query manual ke tabel model_has_roles
        $role = DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_id', $user->id)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->first();

        return $role->name ?? 'staff';
    }
}