<?php

namespace App\Http\Requests\MainHttp\Deposit;

use Illuminate\Foundation\Http\FormRequest;

class PayHeroDepositRequest extends FormRequest
{
    public function rules()
    {
        return [
            'amount' => ['required', 'numeric', 'min:5', 'max:10000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'phone_number' => ['required', 'regex:/^(?:\+?254|0)(?:7|1)\d{8}$/'],
        ];
    }
}
