<?php

namespace App\Services\MainHttp;

use App\Http\Controllers\Controller;
use App\MarketStatus;
use App\Models\Symbols\Options\Symbol;
use App\Models\Symbols\Options\Ticks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SymbolsHistoryService
{
    public function getExch(Request $request){
        $value = Cache::remember('symbols_all', 5, function () {
            $symbols = Symbol::orderBy('percent', 'desc')->get();
            $symbolKeys = $symbols->map(function ($symbol) {
                return $symbol->broker . ':' . $symbol->symbol;
            });
            $marketStatuses = MarketStatus::whereIn('symbol_id', $symbolKeys)->get()->keyBy('symbol_id');
            $symbolsWithTicks = Ticks::whereIn('symbol_id', $symbolKeys)
                ->distinct()
                ->pluck('symbol_id')
                ->flip();

            $symbols = $symbols->map(function($symbol) use ($marketStatuses, $symbolsWithTicks) {
                $symbolKey = $symbol->broker . ':' . $symbol->symbol;
                $market = $marketStatuses->get($symbolKey);
                if((Carbon::now()->hour >= $symbol->work_to or Carbon::now()->hour < $symbol->work_from) and ($symbol->work_from != $symbol->work_to)){
                    $symbol->percent = 0;
                } elseif(!$market or $market->market_status != 'market'){
                    $symbol->percent = 0;
                } elseif (!$symbolsWithTicks->has($symbolKey)){
                    $symbol->percent = 0;
                } elseif($symbol->status == 0){
                    $symbol->percent = 0;
                }
                $symbol->symbol = $symbol->first_symbol . '/' . $symbol->second_symbol;
                return $symbol;
            });
            return $symbols;
        });
        return $value->sortByDesc('percent')->values();
    }
}
