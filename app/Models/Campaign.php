<?php
// app/Models/Campaign.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Campaign extends Model
{
    protected $table = 'campaigns';
    
    protected $fillable = [
        'name',
        'slug',
        'description',
        'category',
        'goal',
        'raised',
        'donors',
        'status',
        'start_date',
        'end_date',
        'image'
    ];

    protected $casts = [
        'goal' => 'decimal:2',
        'raised' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Accessor untuk progress
    public function getProgressAttribute()
    {
        if ($this->goal > 0) {
            return min(round(($this->raised / $this->goal) * 100), 100);
        }
        return 0;
    }

    // Accessor untuk remaining
    public function getRemainingAttribute()
    {
        return max($this->goal - $this->raised, 0);
    }

    // Scope untuk campaign aktif
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    // Scope untuk campaign inactive
    public function scopeInactive($query)
    {
        return $query->where('status', 'Inactive');
    }
}