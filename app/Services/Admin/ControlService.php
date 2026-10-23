<?php

namespace App\Services\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Control\BanActionRequest;
use App\Http\Requests\Admin\Control\ChangeBalanceRequest;
use App\Models\Profile;
use App\Models\AdminAuditLog;
use App\Models\Role;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ControlService
{
    public function getControlInfoServ(Request $request, $id)
    {
        $data = [
            'profile' => Profile::where('user_id', $id)->firstOrFail(),
            'user' => User::where('id', $id)->firstOrFail(),
            'balance' => User::where('id', $id)->firstOrFail()->balance,
        ];
        return ($data);
    }

    public function getUserBanServ(Request $request, $id)
    {
        return (['banned' => User::where('id', $id)->first()->banned]);
    }

    public function changeBalanceServ(ChangeBalanceRequest $request, $id)
    {
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }

        $result = app(SuperAdminService::class)->adjustWallet([
            'user_id' => (int) $id,
            'action' => (int) $request->action === 0 ? 'credit' : 'debit',
            'amount' => $request->balance,
            'reason' => $request->reason,
            'reference' => $request->reference,
        ]);
        $result['message'] = __('locale.admin_control_balance');
        return $result;
    }

    public function banActionServ(BanActionRequest $request, $id)
    {
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }

        $action = $request->action ? $request->action : null;
        if($action == 4){
            $action = 3;
        }
        DB::transaction(function () use ($request, $id, $action) {
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
            if (auth()->id() === (int) $user->id) {
                throw ValidationException::withMessages(['user' => 'You cannot suspend your own account.']);
            }
            $isAdmin = Role::where('user_id', $user->id)->where('role', 1)->exists();
            $access = app(AdminAccessService::class);
            if ($isAdmin && !$access->isSuperAdmin(auth()->user())) {
                throw ValidationException::withMessages(['user' => 'Only a Super Admin can change administrator account status.']);
            }
            if ($isAdmin && $access->isSuperAdmin($user) && $action !== null && $action !== 0) {
                $activeSuperAdmins = Role::where('role', 1)->get()->filter(function ($role) use ($access) {
                    $admin = User::find($role->user_id);
                    return $admin && !$admin->banned && $access->isSuperAdmin($admin);
                })->count();
                if ($activeSuperAdmins <= 1) {
                    throw ValidationException::withMessages(['user' => 'The last active Super Admin cannot be blocked.']);
                }
            }
            if ($action !== null && $action !== 0) {
                $user->auth_version = (int) $user->auth_version + 1;
                $user->remember_token = Str::random(60);
                $user->tokens()->delete();
            }
            $user->banned = $action;
            $user->banned_at = $action ? Carbon::now() : null;
            $user->save();
            AdminAuditLog::record($action ? 'user.blocked' : 'user.unblocked', $request->reason, $user->id, null, ['reason_code' => $action]);
        });
        return (['success' => true, 'message' => __('locale.admin_control_ban')]);
    }
}
