<?php

namespace App\Http\Requests\MainHttp\Withdrawal;

use App\Helpers\Helper;
use App\Rules\IdIfAdminRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalletPayoutRequest extends FormRequest
{
    public function rules()
    {
        $admin = $this->filled('id') && Helper::isAdmin();
        return [
            'id' => [new IdIfAdminRule],
            'reason' => $admin ? 'required|string|min:10|max:1000' : 'nullable|string|max:1000',
            'method' => ['required', Rule::in(['mpesa', 'binance_usdt_bep20'])],
            'amount' => [
                'required',
                'numeric',
                'min:'.config('withdrawal.minimum_amount', 10),
                'max:'.config('withdrawal.maximum_amount', 100000),
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
            'phone_number' => [
                'required_if:method,mpesa',
                'nullable',
                'regex:/^\+254(?:7|1)\d{8}$/',
            ],
            'wallet_address' => [
                'required_if:method,binance_usdt_bep20',
                'nullable',
                'regex:/^0x[a-fA-F0-9]{40}$/',
            ],
            'request_key' => ['required', 'uuid'],
        ];
    }

    public function messages()
    {
        return [
            'phone_number.regex' => 'Enter a valid Kenyan mobile number in +254 format.',
            'wallet_address.regex' => 'Enter a valid BNB Smart Chain (BEP20) address.',
            'amount.regex' => 'The withdrawal amount may have no more than two decimal places.',
        ];
    }
}
