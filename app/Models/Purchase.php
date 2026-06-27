<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $fillable = ['product_id', 'user_id', 'qty', 'unit_cost', 'supplier', 'notes'];
}
