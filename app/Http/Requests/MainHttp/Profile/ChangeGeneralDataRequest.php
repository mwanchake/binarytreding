<?php

namespace App\Http\Requests\MainHttp\Profile;

use App\Rules\IdIfAdminRule;
use App\Helpers\Helper;
use Illuminate\Foundation\Http\FormRequest;

class ChangeGeneralDataRequest extends FormRequest
{

    public function rules()
    {
        $admin = $this->filled('id') && Helper::isAdmin();
        return [
            'id' => [new IdIfAdminRule],
            'reason' => $admin ? 'required|string|min:10|max:1000' : 'nullable|string|max:1000',
            'nickname' => 'string|min:3|max:50|nullable',
            'telegram' => 'string|min:3|max:50|nullable',
            'gender' => 'numeric|min:0|max:1',
            'language' => 'string|min:2|max:50',
        ];
    }
}
