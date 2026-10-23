<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailRequest extends FormRequest
{

    public function rules()
    {
        return [
            'email' => 'required|email|max:191|unique:users,email,'.$this->input('id'),
            'id' => 'required|integer|min:1',
            'reason' => 'required|string|min:10|max:1000',
        ];
    }
}
