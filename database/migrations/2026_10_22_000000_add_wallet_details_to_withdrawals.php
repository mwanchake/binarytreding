<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWalletDetailsToWithdrawals extends Migration
{
    public function up()
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('method')->nullable();
            $table->decimal('fee', 10, 2)->default(0);
            $table->decimal('payout_amount', 10, 2)->nullable();
            $table->string('payout_currency', 3)->nullable();
            $table->decimal('payout_exchange_rate', 12, 6)->nullable();
            $table->string('request_key')->nullable()->unique();
            $table->string('transaction_reference')->nullable()->unique();
        });
    }

    public function down()
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropUnique(['request_key']);
            $table->dropUnique(['transaction_reference']);
            $table->dropColumn([
                'method',
                'fee',
                'payout_amount',
                'payout_currency',
                'payout_exchange_rate',
                'request_key',
                'transaction_reference',
            ]);
        });
    }
}
