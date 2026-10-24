<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedDefaultBinanceBtcUsdtSymbol extends Migration
{
    public function up()
    {
        $symbol = 'BTCUSDT';
        $broker = 'BINANCE';

        if (DB::table('symbols')->where('broker', $broker)->where('symbol', $symbol)->exists()) {
            return;
        }

        $now = now();

        DB::table('symbols')->insert([
            'symbol' => $symbol,
            'first_symbol' => 'BTC',
            'second_symbol' => 'USDT',
            'type' => 1,
            'broker' => $broker,
            'percent' => 90,
            'min_percent' => 65,
            'fixed_percent' => 90,
            'max_percent' => 90,
            'work_from' => 0,
            'work_to' => 0,
            'min_expiration_time' => 60,
            'min_percent_tech' => 20,
            'min_percent_news' => 20,
            'min_percent_user' => 20,
            'status' => 1,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down()
    {
        // Keep the seeded symbol and any related production records on rollback.
    }
}
