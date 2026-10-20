<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRates extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'price',
        'market_rate',
        'effective_rate',
        'markup_percent',
    ];

    protected $casts = [
        'price' => 'decimal:5',
        'market_rate' => 'decimal:6',
        'effective_rate' => 'decimal:6',
        'markup_percent' => 'decimal:4',
    ];
}
