<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateTickQuotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (empty(config('database.connections.bavix::clickhouse.host'))) {
            Schema::create('tick_quotes', function (Blueprint $table) {
                $table->string('symbol_id', 255);
                $table->decimal('price', 20, 5);
                $table->dateTime('created_at');
                $table->index(['symbol_id', 'created_at']);
            });

            return;
        }

        DB::connection('bavix::clickhouse')->statement('CREATE TABLE tick_quotes (
            symbol_id String(255),
            price Decimal(20,5),
            created_at DateTime64(6)
        ) 
        ENGINE = MergeTree() ORDER BY (created_at, symbol_id)');

//        DB::connection('bavix::clickhouse')
//            ->statement('ALTER TABLE tick_quotes ADD INDEX col_index(symbol_id) TYPE minmax GRANULARITY 3');
//        DB::connection('bavix::clickhouse')
//            ->statement('ALTER TABLE quotes ADD INDEX col_index(created_at) TYPE minmax GRANULARITY 3');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (empty(config('database.connections.bavix::clickhouse.host'))) {
            Schema::dropIfExists('tick_quotes');

            return;
        }

        DB::connection('bavix::clickhouse')->statement('DROP TABLE IF EXISTS tick_quotes');
    }
}
