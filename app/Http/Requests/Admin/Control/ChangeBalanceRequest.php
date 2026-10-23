<?php

namespace App\Http\Requests\Admin\Control;

use Illuminate\Foundation\Http\FormRequest;

class ChangeBalanceRequest extends FormRequest
{

    public function rules()
    {
        return [
            'balance' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,4})?$/'],
            'action' => 'required|integer|in:0,1',
            'reason' => 'required|string|min:10|max:1000',
            'reference' => 'required|uuid',
            'confirmation' => 'required|accepted',
        ];
    }
}
