<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhoneRequest extends FormRequest
{

    public function rules()
    {
        return [
            'phone' => 'required|string|max:30',
            'id' => 'required|integer|min:1',
            'reason' => 'required|string|min:10|max:1000',
        ];
    }
}
