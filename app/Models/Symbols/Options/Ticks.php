<?php

namespace App\Models\Symbols\Options;

use App\Models\ClickHouseModel;

class Ticks extends ClickHouseModel
{
    protected $primary_key = null;
    public $incrementing = false;
    protected $table = 'tick_quotes';
    protected $dateFormat = 'Y-m-d H:i:s.u';

    const UPDATED_AT = null;
}
