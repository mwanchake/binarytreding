<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MarketStatus extends Model
{
  protected $primaryKey = 'symbol_id';
  protected $keyType = 'string';
  public $incrementing = false;
  protected $fillable = ['symbol_id', 'market_status'];
}
