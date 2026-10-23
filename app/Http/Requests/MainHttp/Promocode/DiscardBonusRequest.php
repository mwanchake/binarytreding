<?php

namespace App\Http\Requests\MainHttp\Promocode;

use App\Helpers\Helper;
use App\Rules\IdIfAdminRule;
use Illuminate\Foundation\Http\FormRequest;

class DiscardBonusRequest extends FormRequest
{

    public function rules()
    {
        $admin = ($this->filled('id') || $this->route('id')) && Helper::isAdmin();
        return [
            'id' => [new IdIfAdminRule],
            'reason' => $admin ? 'required|string|min:10|max:1000' : 'nullable|string|max:1000',
        ];
    }
}
