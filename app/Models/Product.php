<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'store_id',
        'sku',
        'name',
        'category',
        'color',
        'size',
        'supplier',
        'cost_price',
        'selling_price',
        'stock',
        'min_stock',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'selling_price' => 'integer',
            'stock' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function stockStatus(): string
    {
        if ($this->stock === 0) return 'out';
        if ($this->stock === 1) return 'low';
        return 'ok';
    }

    public function stockBadgeClass(): string
    {
        return match ($this->stockStatus()) {
            'out'   => 'red',
            'low'   => 'amber',
            default => 'green',
        };
    }

    public function stockLabel(): string
    {
        return match ($this->stockStatus()) {
            'out'   => 'Habis',
            'low'   => 'Sedikit',
            default => 'Stok ' . $this->stock,
        };
    }
}
