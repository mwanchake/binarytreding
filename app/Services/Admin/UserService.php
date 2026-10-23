<?php

namespace App\Services\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\GetUserRequest;
use App\Http\Requests\Admin\User\UpdateEmailRequest;
use App\Http\Requests\Admin\User\UpdatePasswordRequest;
use App\Http\Requests\Admin\User\UpdatePhoneRequest;
use App\Models\Models\VerifyRequest;
use App\Models\Profile;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use App\Models\AdminAuditLog;
use Illuminate\Support\Facades\DB;

class UserService
{
    // Верификация телефона
    public function updatePhoneServ(UpdatePhoneRequest $request)
    {
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }
        DB::transaction(function () use ($request) {
            Profile::where('user_id', $request->id)->update(['phone' => $request->phone, 'phone_verify_at' => Carbon::now()]);
            AdminAuditLog::record('user.phone_updated', $request->reason, $request->id);
        });
        return (['success' => true, 'message' => __('locale.admin_user_number_updated')]);
    }

    // Верификация почты
    public function updateEmailServ(UpdateEmailRequest $request)
    {
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }
        DB::transaction(function () use ($request) {
            User::where('id', $request->id)->update(['email' => $request->email, 'email_verified_at' => Carbon::now()]);
            AdminAuditLog::record('user.email_updated', $request->reason, $request->id, null, ['email' => $request->email]);
        });
        return (['success' => true, 'message' => __('locale.admin_user_email_updated')]);
    }

    //Обновление пароля
    public function updatePasswordServ(UpdatePasswordRequest $request)
    {
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }
        $user = User::findOrFail($request->id);
        $status = Password::sendResetLink(['email' => $user->email]);
        AdminAuditLog::record('user.password_reset_requested', 'Administrator requested a secure password reset email.', $user->id);
        return [
            'success' => $status === Password::RESET_LINK_SENT,
            'message' => $status === Password::RESET_LINK_SENT
                ? 'Password reset email requested.'
                : 'Password reset email could not be sent. Check mail configuration and retry.',
        ];
    }

    public function getUserServ(GetUserRequest $request)
    {
        return User::where('id', $request->id)->first();
    }
}
