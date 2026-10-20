<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Quote extends ClickHouseModel
{
    use HasFactory;

    protected $fillable = [
        'symbol_id',
        'high',
        'min',
        'open',
        'close',
        'timeframe',
    ];

}
