<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUsdKesRatesToExchangeRatesTable extends Migration
{
    public function up()
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->decimal('market_rate', 15, 6)->nullable();
            $table->decimal('effective_rate', 15, 6)->nullable();
            $table->decimal('markup_percent', 8, 4)->nullable();
        });

    }

    public function down()
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            $table->dropColumn(['market_rate', 'effective_rate', 'markup_percent']);
        });
    }
}
