<?php

namespace App\Http\Requests\MainHttp\Profile;

use App\Helpers\Helper;
use App\Rules\IdIfAdminRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangeMainDataRequest extends FormRequest
{

    public function rules()
    {
        $admin = false;
        if($this->filled('id') && Helper::isAdmin()){
            $admin = true;
        }

        return [
            'id' => [new IdIfAdminRule],
            'reason' => $admin ? 'required|string|min:10|max:1000' : 'nullable|string|max:1000',
            'name' => 'string|min:3|max:50'.$admin ? '|nullable' : '',
            'last_name' => 'string|min:3|max:50'.$admin ? '|nullable' : '',
            'patronymic' => 'string|min:3|max:50'.$admin ? '|nullable' : '',
            'birth' => 'regex:/\d{2}\-\d{2}\-\d{4}/'.$admin ? '|nullable' : '',
            'address' => 'string|min:10|max:250'.$admin ? '|nullable' : '',
            'document_number' => 'string|min:3|max:250'.$admin ? '|nullable' : '',
        ];
    }
}
