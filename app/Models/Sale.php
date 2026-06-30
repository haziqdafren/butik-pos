<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = [
        'invoice_number',
        'user_id',
        'store_id',
        'stock_source_store_id',
        'discount_approval_id',
        'subtotal',
        'discount_amount',
        'total',
        'amount_paid',
        'change',
        'payment_method',
        'cogs',
        'profit',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'change' => 'integer',
            'cogs' => 'integer',
            'profit' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(DiscountApproval::class, 'discount_approval_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(SaleCorrection::class);
    }

    public function stockSourceStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'stock_source_store_id');
    }
}
