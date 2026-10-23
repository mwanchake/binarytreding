<?php

namespace App\Http\Requests\Admin\Verify;

use Illuminate\Foundation\Http\FormRequest;

class UnVerifyAccountRequest extends FormRequest
{

    public function rules()
    {
        return [
            'id' => 'numeric|min:1',
            'reason' => 'required|string|min:10|max:1000',
        ];
    }
}
