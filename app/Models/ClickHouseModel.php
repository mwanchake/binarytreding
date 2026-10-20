<?php

namespace App\Models;

if (empty(config('database.connections.bavix::clickhouse.host'))) {
    abstract class ClickHouseModel extends \Illuminate\Database\Eloquent\Model
    {
    }
} else {
    abstract class ClickHouseModel extends \Bavix\LaravelClickHouse\Database\Eloquent\Model
    {
    }
}
