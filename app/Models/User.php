<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;
use Spatie\Permission\Traits\HasRoles; // WAJIB ADA!

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory, HasRoles; // HasRoles HARUS ADA

    protected $fillable = [
        'name', 'email', 'password', 'api_token', 'avatar', 'position'
    ];

    protected $hidden = [
        'password', 'api_token',
    ];

    public function sessions()
    {
        return $this->hasMany(Session::class);
    }
}