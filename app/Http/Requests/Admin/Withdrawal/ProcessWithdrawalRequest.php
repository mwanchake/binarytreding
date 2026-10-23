<?php

namespace App\Http\Requests\Admin\Withdrawal;

use Illuminate\Foundation\Http\FormRequest;

class ProcessWithdrawalRequest extends FormRequest
{

    public function rules()
    {
        return [
            'id' => 'required|integer|min:1',
            'text' => 'required|string|min:1',
            'status' => 'required|integer|in:1,2,3,4',
            'comment' => 'required_if:status,2,3,4|string|min:10|max:1000',
            'transaction_reference' => 'required_if:status,1|string|max:191',
        ];
    }
}
