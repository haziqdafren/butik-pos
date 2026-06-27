<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountApproval extends Model
{
    protected $fillable = [
        'requested_by',
        'approved_by',
        'type',
        'value',
        'subtotal',
        'amount',
        'reason',
        'status',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'subtotal' => 'integer',
            'amount' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
