<?php

namespace App\Console\Commands;

use App\Services\MainHttp\ExchangeRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateExchangeRates extends Command
{
    protected $signature = 'rates:update';

    protected $description = 'Fetch and store the latest USD/KES exchange rate.';

    public function handle(ExchangeRateService $exchangeRateService)
    {
        try {
            $rate = $exchangeRateService->updateUsdKesRate();
        } catch (Throwable $exception) {
            Log::error('Failed to update the USD/KES exchange rate.', [
                'message' => $exception->getMessage(),
            ]);
            $this->error('Failed to update USD/KES exchange rate: '.$exception->getMessage());

            return 1;
        }

        Log::info('Updated the USD/KES exchange rate.', [
            'market_rate' => $rate->market_rate,
            'effective_rate' => $rate->effective_rate,
            'markup_percent' => $rate->markup_percent,
        ]);
        $this->info(sprintf(
            'USD/KES rate updated. Market: %s, effective: %s (markup: %s%%).',
            $rate->market_rate,
            $rate->effective_rate,
            $rate->markup_percent
        ));

        return 0;
    }
}
