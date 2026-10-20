<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPayHeroReferencesToDepositsTable extends Migration
{
    public function up()
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('gateway_reference')->nullable()->unique();
            $table->string('checkout_request_id')->nullable()->unique();
        });
    }

    public function down()
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['gateway_reference', 'checkout_request_id']);
        });
    }
}
