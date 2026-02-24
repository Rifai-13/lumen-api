<?php
// app/Models/Session.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Session extends Model
{
    protected $table = 'sessions';
    protected $primaryKey = 'id';
    public $incrementing = false; // Karena id bukan auto-increment
    protected $keyType = 'string'; // Karena id adalah string

    protected $fillable = [
        'id', 'user_id', 'ip_address', 'user_agent', 
        'payload', 'last_activity', 'expires_at'
    ];

    protected $casts = [
        'last_activity' => 'integer',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid()
    {
        return $this->expires_at && Carbon::now()->lt($this->expires_at);
    }
}