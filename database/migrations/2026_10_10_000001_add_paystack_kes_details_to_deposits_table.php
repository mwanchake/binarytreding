<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaystackKesDetailsToDepositsTable extends Migration
{
    public function up()
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->decimal('usd_amount', 10, 2)->nullable();
            $table->decimal('kes_amount', 15, 2)->nullable();
            $table->decimal('exchange_rate', 15, 6)->nullable();
            $table->string('phone_number', 20)->nullable();
        });
    }

    public function down()
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['usd_amount', 'kes_amount', 'exchange_rate', 'phone_number']);
        });
    }
}
