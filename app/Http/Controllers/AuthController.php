<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'api_token' => Str::random(40)
        ]);

        return response()->json(['message' => 'Registrasi Berhasil', 'data' => $user], 201);
    }

    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {
            $token = Str::random(40);
            $user->update(['api_token' => $token]);

            return response()->json([
                'message' => 'Login Berhasil',
                'token' => $token,
                'user' => $user
            ]);
        }

        return response()->json(['message' => 'Email atau Password salah'], 401);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        
        if ($user) {
            $user->api_token = null;
            $user->save();
            
            return response()->json([
                'message' => 'Logout berhasil'
            ]);
        }
        
        return response()->json([
            'message' => 'User tidak ditemukan'
        ], 404);
    }

    public function user(Request $request)
    {
        $user = $request->user();

        $user->load('roles');

        Log::info('User roles:', [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->toArray()
        ]);

        $roleName = $user->roles->first()?->name ?? 'staff';

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $roleName,
            'roles' => $user->roles->pluck('name')
        ]);
    }
}