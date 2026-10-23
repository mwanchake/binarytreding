<?php

namespace App\Http\Requests\Admin\Control;

use Illuminate\Foundation\Http\FormRequest;

class BanActionRequest extends FormRequest
{

    public function rules()
    {
        return [
            'action' => 'required|integer|min:0|max:4',
            'reason' => 'required|string|min:10|max:1000',
        ];
    }
}
