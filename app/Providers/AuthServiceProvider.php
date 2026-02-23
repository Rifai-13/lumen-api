<?php
// app/Providers/AuthServiceProvider.php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        $this->app['auth']->viaRequest('api', function ($request) {
            // Cek token dari header Authorization
            $token = $request->header('Authorization');
            
            if ($token && str_starts_with($token, 'Bearer ')) {
                $token = substr($token, 7);
                
                // Cari user berdasarkan api_token
                return User::where('api_token', $token)->first();
            }
            
            return null;
        });
    }
}