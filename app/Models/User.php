<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Spatie\Permission\Traits\HasRoles;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory, HasRoles;

    protected $fillable = [
        'name', 
        'email', 
        'password',
        'api_token'  // ✅ Token ada di sini, sesuai tabel
    ];

    protected $hidden = [
        'password',
        'api_token'  // Sembunyikan dari response JSON
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    // Relasi ke donations (jika user berdonasi)
    public function donations()
    {
        return $this->hasMany(Donation::class, 'user_id');
    }
}