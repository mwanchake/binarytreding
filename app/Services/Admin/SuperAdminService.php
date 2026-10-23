<?php

namespace App\Services\Admin;

use App\Events\ChangeBalance;
use App\Models\AdminAccount;
use App\Models\AdminAuditLog;
use App\Models\AdminWalletAdjustment;
use App\Models\Deposit;
use App\Models\Profile;
use App\Models\Referral;
use App\Models\Role;
use App\Models\Withdrawal;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SuperAdminService
{
    private $access;

    public function __construct(AdminAccessService $access)
    {
        $this->access = $access;
    }

    public function dashboard(Request $request)
    {
        $dates = $this->dateRange($request);
        $users = User::query();
        $deposits = Deposit::query();
        $withdrawals = Withdrawal::query();
        $trades = DB::table('order_history_1');
        foreach ([$users, $deposits, $withdrawals, $trades] as $query) {
            $this->applyDateRange($query, $dates);
        }

        return [
            'users' => $users->count(),
            'active_users' => User::where(function ($query) {
                $query->whereNull('banned')->orWhere('banned', 0);
            })->count(),
            'blocked_users' => User::whereNotNull('banned')->where('banned', '<>', 0)->count(),
            'deposits' => $deposits->count(),
            'deposit_amount' => round((float) (clone $deposits)->where('status', 1)->sum('amount'), 2),
            'withdrawals' => $withdrawals->count(),
            'withdrawal_amount' => round((float) (clone $withdrawals)->where('status', 1)->sum('amount'), 2),
            'pending_deposits' => (clone $deposits)->where('status', 0)->count(),
            'pending_withdrawals' => (clone $withdrawals)->whereIn('status', [0, 3])->count(),
            'wallet_balances' => round((float) User::sum('balance'), 2),
            'trades' => $trades->count(),
            'recent_audit' => AdminAuditLog::with(['actor:id,email', 'affectedUser:id,email'])
                ->latest('id')->limit(10)->get(),
            'alerts' => [
                'pending_deposits' => Deposit::where('status', 0)->count(),
                'pending_withdrawals' => Withdrawal::whereIn('status', [0, 3])->count(),
                'failed_withdrawals' => Withdrawal::where('status', 4)->count(),
            ],
            'date_range' => $dates,
        ];
    }

    public function users(Request $request)
    {
        $dates = $this->dateRange($request);
        $query = User::withTrashed()->with('profile:user_id,user_verify_at')->select([
            'id', 'name', 'email', 'balance', 'banned', 'banned_at', 'referer_id',
            'created_at', 'deleted_at',
        ]);

        if ($request->filled('search')) {
            $term = trim($request->input('search'));
            $query->where(function ($builder) use ($term) {
                $builder->where('email', 'like', '%'.$term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%')
                    ->orWhere('id', is_numeric($term) ? '=' : 'like', is_numeric($term) ? (int) $term : '%'.$term.'%');
            });
        }
        if ($request->input('status') === 'blocked') {
            $query->whereNotNull('banned')->where('banned', '<>', 0);
        } elseif ($request->input('status') === 'active') {
            $query->where(function ($builder) {
                $builder->whereNull('banned')->orWhere('banned', 0);
            })->whereNull('deleted_at');
        } elseif ($request->input('status') === 'deleted') {
            $query->whereNotNull('deleted_at');
        }
        $this->applyDateRange($query, $dates);

        return $query->orderByDesc('id')->paginate(min(max((int) $request->input('per_page', 25), 1), 100));
    }

    public function userDetails($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        return [
            'user' => $user->only([
                'id', 'name', 'email', 'balance', 'bonus', 'all_turnover', 'left_turnover',
                'banned', 'banned_at', 'referer_id', 'created_at', 'deleted_at',
            ]),
            'profile' => Profile::where('user_id', $user->id)->first(),
            'deposits' => Deposit::where('user_id', $user->id)->latest('id')->limit(20)->get(),
            'withdrawals' => Withdrawal::where('user_id', $user->id)->latest('id')->limit(20)->get(),
            'trades' => DB::table('order_history_1')->where('user_id', $user->id)->latest('id')->limit(20)->get(),
            'referrals' => User::where('referer_id', $user->id)->count(),
            'referrer' => $user->referer_id ? User::withTrashed()->find($user->referer_id, ['id', 'email']) : null,
        ];
    }

    public function createUser(array $data)
    {
        return DB::transaction(function () use ($data) {
            $user = new User();
            $user->name = $data['name'] ?? null;
            $user->email = $data['email'];
            $user->password = Hash::make(Str::random(64));
            do {
                $user->token = Str::random(10);
            } while (User::where('token', $user->token)->exists());
            $user->balance = 0;
            $user->save();

            $profile = new Profile();
            $profile->user_id = $user->id;
            $profile->save();

            $referral = new Referral();
            $referral->user_id = $user->id;
            $referral->save();

            AdminAuditLog::record('user.created', $data['reason'], $user->id, null, ['email' => $user->email]);
            return $user;
        });
    }

    public function updateUser($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $user = User::findOrFail($id);
            $before = ['name' => $user->name, 'email' => $user->email];
            $user->name = $data['name'];
            if ($user->email !== $data['email']) {
                $user->email = $data['email'];
                $user->email_verified_at = null;
            }
            $user->save();
            AdminAuditLog::record('user.updated', $data['reason'], $user->id, null, [
                'before' => $before,
                'after' => ['name' => $user->name, 'email' => $user->email],
            ]);
            return $user->only(['id', 'name', 'email']);
        });
    }

    public function setUserStatus($id, $status, $reason)
    {
        return DB::transaction(function () use ($id, $status, $reason) {
            $user = User::findOrFail($id);
            $blocked = $status === 'blocked';
            if (auth()->id() === (int) $user->id && $blocked) {
                throw ValidationException::withMessages(['user' => 'You cannot block your own account.']);
            }
            if ($this->access->isAdmin($user) && !$this->access->isSuperAdmin(auth()->user())) {
                throw ValidationException::withMessages(['user' => 'Only a Super Admin can change an administrator account status.']);
            }
            if ($blocked && $this->access->isAdmin($user) && $this->access->isSuperAdmin($user)
                && $this->superAdminCount() <= 1) {
                throw ValidationException::withMessages(['user' => 'The last Super Admin cannot be blocked.']);
            }

            $user->banned = $blocked ? 1 : null;
            $user->banned_at = $blocked ? Carbon::now() : null;
            $user->save();
            if ($blocked) {
                $user->tokens()->delete();
                $user->remember_token = Str::random(60);
                $user->auth_version = (int) $user->auth_version + 1;
            }
            $user->save();

            AdminAuditLog::record(
                $blocked ? 'user.blocked' : 'user.unblocked',
                $reason,
                $user->id
            );
            return ['success' => true, 'blocked' => $blocked];
        });
    }

    public function deleteUser($id, $reason)
    {
        return DB::transaction(function () use ($id, $reason) {
            $user = User::withTrashed()->lockForUpdate()->findOrFail($id);
            if (auth()->id() === (int) $user->id) {
                throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
            }
            if ($this->access->isAdmin($user)) {
                if (!$this->access->isSuperAdmin(auth()->user())) {
                    throw ValidationException::withMessages(['user' => 'Only a Super Admin can remove an administrator account.']);
                }
                if ($this->access->isSuperAdmin($user) && $this->superAdminCount() <= 1) {
                    throw ValidationException::withMessages(['user' => 'The last Super Admin cannot be deleted.']);
                }
                Role::where('user_id', $user->id)->delete();
                AdminAccount::where('user_id', $user->id)->delete();
            }
            $user->tokens()->delete();
            if (!$user->trashed()) {
                $user->auth_version = (int) $user->auth_version + 1;
                $user->save();
                $user->delete();
            }
            AdminAuditLog::record('user.soft_deleted', $reason, $user->id);
            return ['success' => true, 'message' => 'Account deactivated. Financial and trading records were retained.'];
        });
    }

    public function adminAccounts()
    {
        return User::join('roles', 'roles.user_id', '=', 'users.id')
            ->where('roles.role', 1)
            ->whereNull('users.deleted_at')
            ->leftJoin('admin_accounts', 'admin_accounts.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'users.email', 'admin_accounts.role_name', 'admin_accounts.permissions')
            ->orderBy('users.id')
            ->get()
            ->map(function ($account) {
                $isLegacySuper = $account->role_name === null;
                $account->role_name = $isLegacySuper ? 'super_admin' : $account->role_name;
                $account->permissions = $isLegacySuper ? AdminAccessService::PERMISSIONS : (json_decode($account->permissions, true) ?: []);
                return $account;
            });
    }

    public function assignAdminRole($userId, $roleName, array $permissions, $reason)
    {
        return DB::transaction(function () use ($userId, $roleName, $permissions, $reason) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $existing = AdminAccount::where('user_id', $user->id)->first();
            $wasSuperAdmin = $this->access->isAdmin($user) && $this->access->isSuperAdmin($user);
            $willBeSuperAdmin = $roleName === 'super_admin';

            if (auth()->id() === (int) $user->id && !$willBeSuperAdmin) {
                throw ValidationException::withMessages(['user_id' => 'You cannot remove your own Super Admin access.']);
            }
            if ($wasSuperAdmin && !$willBeSuperAdmin && $this->superAdminCount() <= 1) {
                throw ValidationException::withMessages(['role' => 'The last Super Admin cannot be demoted.']);
            }

            Role::firstOrCreate(['user_id' => $user->id], ['role' => 1])->update(['role' => 1]);
            AdminAccount::updateOrCreate(
                ['user_id' => $user->id],
                ['role_name' => $roleName, 'permissions' => $willBeSuperAdmin ? AdminAccessService::PERMISSIONS : $permissions]
            );
            AdminAuditLog::record('admin.role_changed', $reason, $user->id, null, [
                'previous_role' => $existing ? $existing->role_name : 'super_admin',
                'role' => $roleName,
                'permissions' => $willBeSuperAdmin ? AdminAccessService::PERMISSIONS : $permissions,
            ]);
            return ['success' => true];
        });
    }

    public function auditLogs(Request $request)
    {
        $query = AdminAuditLog::with(['actor:id,email', 'affectedUser:id,email']);
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('user_id')) {
            $query->where('affected_user_id', $request->input('user_id'));
        }
        $this->applyDateRange($query, $this->dateRange($request));
        return $query->latest('id')->paginate(min(max((int) $request->input('per_page', 50), 1), 100));
    }

    public function adjustWallet(array $data)
    {
        $result = DB::transaction(function () use ($data) {
            $existing = AdminWalletAdjustment::where('reference', $data['reference'])->first();
            if ($existing) {
                if ((int) $existing->user_id !== (int) $data['user_id']
                    || $existing->action !== $data['action']
                    || round((float) $existing->amount, 4) !== round((float) $data['amount'], 4)) {
                    throw ValidationException::withMessages(['reference' => 'This reference was already used for a different adjustment.']);
                }
                return ['duplicate' => true, 'adjustment' => $existing];
            }

            $user = User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            $before = round((float) $user->balance, 4);
            $amount = round((float) $data['amount'], 4);
            $after = $data['action'] === 'credit' ? $before + $amount : $before - $amount;
            if ($after < 0) {
                throw ValidationException::withMessages(['amount' => 'The debit exceeds the available wallet balance.']);
            }

            $user->balance = $after;
            $user->save();
            $adjustment = new AdminWalletAdjustment();
            $adjustment->reference = $data['reference'];
            $adjustment->user_id = $user->id;
            $adjustment->actor_user_id = auth()->id();
            $adjustment->action = $data['action'];
            $adjustment->amount = $amount;
            $adjustment->balance_before = $before;
            $adjustment->balance_after = $after;
            $adjustment->reason = $data['reason'];
            $adjustment->save();

            AdminAuditLog::record('wallet.'.$data['action'], $data['reason'], $user->id, $data['reference'], [
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
            ]);
            return ['user' => $user, 'adjustment' => $adjustment, 'balance' => $after];
        });

        if (isset($result['user'])) {
            broadcast(new ChangeBalance($result['balance'], $result['user']));
        }
        return [
            'success' => true,
            'duplicate' => !empty($result['duplicate']),
            'adjustment' => $result['adjustment'],
            'balance' => isset($result['balance']) ? $result['balance'] : null,
        ];
    }

    public function exportFinancialReport(Request $request)
    {
        $type = $request->input('type');
        $dates = $this->dateRange($request);
        if (!in_array($type, ['deposits', 'withdrawals', 'wallet_adjustments'], true)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported report type.']);
        }

        AdminAuditLog::record('finance.report_exported', 'Authorized financial report export requested.', null, null, [
            'type' => $type,
            'from' => $dates['from'],
            'to' => $dates['to'],
        ]);

        $filename = $type.'_'.Carbon::now()->format('Ymd_His').'.csv';
        return response()->streamDownload(function () use ($type, $dates) {
            $output = fopen('php://output', 'w');
            if ($type === 'deposits') {
                fputcsv($output, ['id', 'user_id', 'email', 'amount', 'status', 'system_id', 'reference', 'created_at']);
                $query = DB::table('deposits')->leftJoin('users', 'users.id', '=', 'deposits.user_id')
                    ->select('deposits.id', 'deposits.user_id', 'users.email', 'deposits.amount', 'deposits.status', 'deposits.system_id', 'deposits.reference', 'deposits.created_at');
            } elseif ($type === 'withdrawals') {
                fputcsv($output, ['id', 'user_id', 'email', 'amount', 'fee', 'payout_amount', 'payout_currency', 'status', 'transaction_reference', 'created_at']);
                $query = DB::table('withdrawals')->leftJoin('users', 'users.id', '=', 'withdrawals.user_id')
                    ->select('withdrawals.id', 'withdrawals.user_id', 'users.email', 'withdrawals.amount', 'withdrawals.fee', 'withdrawals.payout_amount', 'withdrawals.payout_currency', 'withdrawals.status', 'withdrawals.transaction_reference', 'withdrawals.created_at');
            } else {
                fputcsv($output, ['id', 'reference', 'user_id', 'actor_user_id', 'action', 'amount', 'balance_before', 'balance_after', 'reason', 'created_at']);
                $query = DB::table('admin_wallet_adjustments')
                    ->select('id', 'reference', 'user_id', 'actor_user_id', 'action', 'amount', 'balance_before', 'balance_after', 'reason', 'created_at');
            }
            $createdAtColumn = $type === 'deposits'
                ? 'deposits.created_at'
                : ($type === 'withdrawals' ? 'withdrawals.created_at' : 'created_at');
            $this->applyDateRange($query, $dates, $createdAtColumn);
            foreach ($query->orderBy($createdAtColumn)->cursor() as $row) {
                $values = array_map(function ($value) {
                    if (is_string($value) && !is_numeric($value)
                        && preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
                        return "'".$value;
                    }
                    return $value;
                }, (array) $row);
                fputcsv($output, $values);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function superAdminCount()
    {
        return Role::where('role', 1)->get()->filter(function ($role) {
            $user = User::find($role->user_id);
            return $user && !$user->banned && $this->access->isSuperAdmin($user);
        })->count();
    }

    private function dateRange(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        return [
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];
    }

    private function applyDateRange($query, array $dates, $column = 'created_at')
    {
        if ($dates['from']) {
            $query->where($column, '>=', Carbon::parse($dates['from'])->startOfDay());
        }
        if ($dates['to']) {
            $query->where($column, '<=', Carbon::parse($dates['to'])->endOfDay());
        }
    }
}
