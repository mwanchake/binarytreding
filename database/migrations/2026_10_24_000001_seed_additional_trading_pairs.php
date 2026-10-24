<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedAdditionalTradingPairs extends Migration
{
    public function up()
    {
        $pairs = [
            ['BINANCE', 'ETH', 'USDT'],
            ['BINANCE', 'BNB', 'USDT'],
            ['BINANCE', 'SOL', 'USDT'],
            ['BINANCE', 'XRP', 'USDT'],
            ['BINANCE', 'DOGE', 'USDT'],
            ['BINANCE', 'ADA', 'USDT'],
            ['BINANCE', 'TRX', 'USDT'],
            ['BINANCE', 'LTC', 'USDT'],
            ['BINANCE', 'BCH', 'USDT'],
            ['COINBASE', 'ETH', 'USD'],
            ['COINBASE', 'SOL', 'USD'],
            ['COINBASE', 'BTC', 'USD'],
            ['COINBASE', 'XRP', 'USD'],
            ['COINBASE', 'LTC', 'USD'],
        ];

        $now = now();

        foreach ($pairs as [$broker, $firstSymbol, $secondSymbol]) {
            $symbol = $firstSymbol . $secondSymbol;

            if (DB::table('symbols')->where('broker', $broker)->where('symbol', $symbol)->exists()) {
                continue;
            }

            DB::table('symbols')->insert([
                'symbol' => $symbol,
                'first_symbol' => $firstSymbol,
                'second_symbol' => $secondSymbol,
                'type' => 1,
                'broker' => $broker,
                'percent' => 80,
                'min_percent' => 65,
                'fixed_percent' => 80,
                'max_percent' => 85,
                'work_from' => 0,
                'work_to' => 0,
                'min_expiration_time' => 60,
                'min_percent_tech' => 0,
                'min_percent_news' => 0,
                'min_percent_user' => 0,
                'status' => 1,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        // Keep seeded symbols and any related production records on rollback.
    }
}
