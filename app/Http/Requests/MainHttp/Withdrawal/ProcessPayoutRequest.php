<?php

namespace App\Http\Requests\MainHttp\Withdrawal;

use App\Rules\IdIfAdminRule;
use App\Rules\UserIfAdminIdRule;
use Illuminate\Foundation\Http\FormRequest;

class ProcessPayoutRequest extends WalletPayoutRequest
{
}
