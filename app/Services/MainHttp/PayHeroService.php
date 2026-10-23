<?php

namespace App\Services\MainHttp;

use App\Models\Deposit;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayHeroService
{
    private const API_BASE_URL = 'https://backend.payhero.co.ke/api/v2';

    public function initiateStkPush(Deposit $deposit)
    {
        $username = config('deposit.payhero_api_username');
        $password = config('deposit.payhero_api_password');
        $channelId = config('deposit.payhero_channel_id');

        if (!$username || !$password || !$channelId) {
            throw new RuntimeException('PayHero is not configured.');
        }

        $response = Http::withBasicAuth($username, $password)
            ->acceptJson()
            ->withOptions([
                'verify' => config('deposit.exchange_rate_ca_bundle') ?: true,
            ])
            ->timeout(15)
            ->post(self::API_BASE_URL.'/payments', [
                'amount' => (int) round((float) $deposit->kes_amount),
                'phone_number' => $this->toLocalPhoneNumber($deposit->phone_number),
                'channel_id' => (int) $channelId,
                'provider' => 'm-pesa',
                'external_reference' => $deposit->reference,
                'customer_name' => $deposit->user->name,
                'callback_url' => config('deposit.payhero_callback_url') ?: route('payhero.webhook'),
            ]);

        $data = $response->json();
        $gatewayReference = data_get($data, 'reference');
        $checkoutRequestId = data_get($data, 'CheckoutRequestID');
        if (!$response->successful()
            || data_get($data, 'success') !== true
            || !is_string($gatewayReference)
            || $gatewayReference === ''
            || !is_string($checkoutRequestId)
            || $checkoutRequestId === '') {
            throw new RuntimeException(
                data_get($data, 'message', 'PayHero did not accept the STK Push request.')
            );
        }

        return [
            'reference' => $gatewayReference,
            'checkout_request_id' => $checkoutRequestId,
        ];
    }

    public function getTransactionStatus($reference)
    {
        $username = config('deposit.payhero_api_username');
        $password = config('deposit.payhero_api_password');
        if (!$username || !$password) {
            throw new RuntimeException('PayHero is not configured.');
        }

        $response = Http::withBasicAuth($username, $password)
            ->acceptJson()
            ->withOptions([
                'verify' => config('deposit.exchange_rate_ca_bundle') ?: true,
            ])
            ->timeout(15)
            ->get(self::API_BASE_URL.'/transaction-status', [
                'reference' => $reference,
            ]);

        $data = $response->json();
        if (!$response->successful() || !is_array($data)) {
            throw new RuntimeException('Unable to retrieve transaction status from PayHero.');
        }

        return $data;
    }

    public function processWebhook(array $payload)
    {
        $response = data_get($payload, 'response', []);
        $externalReference = data_get($response, 'ExternalReference', data_get($payload, 'ExternalReference'));
        $callbackAmount = data_get($response, 'Amount', data_get($payload, 'Amount'));
        $callbackStatus = data_get($response, 'Status', data_get($payload, 'Status'));
        $checkoutRequestId = data_get($response, 'CheckoutRequestID', data_get($payload, 'CheckoutRequestID'));
        $mpesaReceiptNumber = data_get($response, 'MpesaReceiptNumber', data_get($payload, 'MpesaReceiptNumber'));

        if (!is_string($externalReference) || $externalReference === '' || !is_numeric($callbackAmount)) {
            throw new RuntimeException('PayHero callback is missing the deposit reference or amount.');
        }

        $deposit = Deposit::where('reference', $externalReference)
            ->where('system_id', 'payhero')
            ->first();
        if (!$deposit) {
            throw new RuntimeException('No matching PayHero deposit was found.');
        }

        if ((int) round((float) $callbackAmount) !== (int) round((float) $deposit->kes_amount)) {
            throw new RuntimeException('The PayHero callback amount does not match the pending deposit.');
        }

        if ($checkoutRequestId && $deposit->checkout_request_id
            && !hash_equals((string) $deposit->checkout_request_id, (string) $checkoutRequestId)) {
            throw new RuntimeException('The PayHero callback does not match the pending STK request.');
        }

        if (in_array(strtoupper(trim((string) $callbackStatus)), [
            'FAILED',
            'CANCELLED',
            'CANCELED',
            'TIMEOUT',
            'TIMED OUT',
            'EXPIRED',
            'REJECTED',
        ], true)) {
            return [
                'deposit' => $deposit,
                'successful' => false,
                'terminal_failure' => true,
                'message' => 'PayHero reported that the M-Pesa payment failed.',
            ];
        }

        $gatewayReference = $deposit->gateway_reference;
        $statusLookupReference = $gatewayReference;
        if (!$statusLookupReference) {
            if (strcasecmp((string) $callbackStatus, 'Success') !== 0
                || !is_string($mpesaReceiptNumber)
                || $mpesaReceiptNumber === '') {
                throw new RuntimeException('The PayHero deposit is missing its gateway reference.');
            }

            $statusLookupReference = $mpesaReceiptNumber;
        }

        $status = $this->getTransactionStatus($statusLookupReference);
        $gatewayStatus = strtoupper((string) data_get($status, 'status'));
        $verifiedGatewayReference = data_get($status, 'reference');
        if (!is_string($verifiedGatewayReference) || $verifiedGatewayReference === ''
            || ($gatewayReference && !hash_equals((string) $gatewayReference, $verifiedGatewayReference))) {
            throw new RuntimeException('The PayHero transaction status does not match this deposit.');
        }

        $verifiedExternalReference = data_get($status, 'external_reference');
        if (!is_string($verifiedExternalReference)
            || !hash_equals((string) $deposit->reference, $verifiedExternalReference)) {
            throw new RuntimeException('The PayHero transaction status does not match this deposit.');
        }

        $verifiedAmount = data_get($status, 'amount');
        if (!is_numeric($verifiedAmount)
            || (int) round((float) $verifiedAmount) !== (int) round((float) $deposit->kes_amount)) {
            throw new RuntimeException('The PayHero transaction status amount does not match this deposit.');
        }

        if (strcasecmp((string) $callbackStatus, 'Success') !== 0) {
            if ($gatewayStatus === 'FAILED') {
                return [
                    'deposit' => $deposit,
                    'successful' => false,
                    'terminal_failure' => true,
                    'message' => 'PayHero reported that the M-Pesa payment failed.',
                ];
            }

            return [
                'deposit' => $deposit,
                'successful' => false,
                'terminal_failure' => false,
                'message' => 'PayHero reported that the M-Pesa payment did not succeed.',
            ];
        }

        if (data_get($status, 'success') !== true
            || $gatewayStatus !== 'SUCCESS') {
            throw new RuntimeException('PayHero has not confirmed this M-Pesa payment.');
        }

        if (!$gatewayReference) {
            $deposit->gateway_reference = $verifiedGatewayReference;
            if (!$deposit->checkout_request_id && is_string($checkoutRequestId) && $checkoutRequestId !== '') {
                $deposit->checkout_request_id = $checkoutRequestId;
            }
            $deposit->save();
        }

        return [
            'deposit' => $deposit,
            'successful' => true,
            'terminal_failure' => false,
            'message' => 'PayHero confirmed the M-Pesa payment.',
        ];
    }

    private function toLocalPhoneNumber($phoneNumber)
    {
        $digits = preg_replace('/\D+/', '', (string) $phoneNumber);
        if (substr($digits, 0, 3) === '254') {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }
}
