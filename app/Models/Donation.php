<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Donation extends Model
{
    protected $table = 'donations';
    
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'amount',
        'campaign',
        'payment_method',
        'payment_provider',
        'status',
        'transaction_id',
        'notes'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    // Campaign constants
    const CAMPAIGN_EDUCATION = 'education';
    const CAMPAIGN_HEALTHCARE = 'healthcare';
    const CAMPAIGN_DISASTER = 'disaster';

    // Helper methods
    public function isSuccess()
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function markAsSuccess($transactionId = null)
    {
        $this->status = self::STATUS_SUCCESS;
        if ($transactionId) {
            $this->transaction_id = $transactionId;
        }
        $this->save();
    }

    public function markAsFailed()
    {
        $this->status = self::STATUS_FAILED;
        $this->save();
    }

    // Scope methods
    public function scopeByCampaign($query, $campaign)
    {
        return $query->where('campaign', $campaign);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSuccess($query)
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::now()->toDateString());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', Carbon::now()->month);
    }
}