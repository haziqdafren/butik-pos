<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSettings extends Model
{
    protected $table = 'store_settings';

    protected $fillable = ['key', 'value'];
}
