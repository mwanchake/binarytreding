<?php

namespace App\Services\MainHttp;

use App\Models\ExchangeRates;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExchangeRateService
{
    private const MAX_RATE_AGE_MINUTES = 30;

    public function updateUsdKesRate()
    {
        $response = Http::acceptJson()
            ->withOptions([
                'verify' => config('deposit.exchange_rate_ca_bundle') ?: true,
            ])
            ->timeout(10)
            ->get(config('deposit.exchange_rate_api_url'));

        $responseData = $response->json();
        $marketRate = data_get($responseData, 'rates.KES');
        if (!$response->successful()
            || data_get($responseData, 'result') !== 'success'
            || data_get($responseData, 'base_code') !== 'USD'
            || !$this->isValidRate($marketRate)) {
            throw new RuntimeException('The exchange-rate provider returned an unsuccessful or invalid USD/KES rate.');
        }

        $markupPercent = config('deposit.exchange_markup_percent');
        if (!is_numeric($markupPercent) || !is_finite((float) $markupPercent) || (float) $markupPercent < 0) {
            throw new RuntimeException('The configured exchange-rate markup is invalid.');
        }

        $marketRate = number_format((float) $marketRate, 6, '.', '');
        $markupPercent = number_format((float) $markupPercent, 4, '.', '');
        $effectiveRate = number_format(
            (float) $marketRate * (1 + (float) $markupPercent / 100),
            6,
            '.',
            ''
        );

        $exchangeRate = ExchangeRates::firstOrNew(['symbol' => 'USD_KES']);
        $exchangeRate->price = $effectiveRate;
        $exchangeRate->market_rate = $marketRate;
        $exchangeRate->effective_rate = $effectiveRate;
        $exchangeRate->markup_percent = $markupPercent;
        $exchangeRate->save();

        return $exchangeRate;
    }

    public function getLatestUsdKesRate()
    {
        $exchangeRate = ExchangeRates::where('symbol', 'USD_KES')
            ->whereNotNull('effective_rate')
            ->orderByDesc('updated_at')
            ->first();

        if (!$exchangeRate
            || !$this->isValidRate($exchangeRate->market_rate)
            || !$this->isValidRate($exchangeRate->effective_rate)
            || !is_numeric($exchangeRate->markup_percent)
            || (float) $exchangeRate->markup_percent < 0) {
            throw new ExchangeRateUnavailableException('The USD/KES exchange rate is not available yet.');
        }

        return $exchangeRate;
    }

    public function getFreshUsdKesRate()
    {
        $exchangeRate = ExchangeRates::where('symbol', 'USD_KES')
            ->whereNotNull('effective_rate')
            ->orderByDesc('updated_at')
            ->first();

        if ($exchangeRate
            && $exchangeRate->updated_at
            && $exchangeRate->updated_at->greaterThan(now()->subMinutes(self::MAX_RATE_AGE_MINUTES))
            && $this->isUsableRate($exchangeRate)) {
            return $exchangeRate;
        }

        return $this->updateUsdKesRate();
    }

    private function isValidRate($rate)
    {
        return is_numeric($rate) && is_finite((float) $rate) && (float) $rate > 0;
    }

    private function isUsableRate($exchangeRate)
    {
        return $this->isValidRate($exchangeRate->market_rate)
            && $this->isValidRate($exchangeRate->effective_rate)
            && is_numeric($exchangeRate->markup_percent)
            && (float) $exchangeRate->markup_percent >= 0;
    }
}
