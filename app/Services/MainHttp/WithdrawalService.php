<?php

namespace App\Services\MainHttp;

use App\Events\ChangeBalance;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MainHttp\Withdrawal\GetAccountDataRequest;
use App\Http\Requests\MainHttp\Withdrawal\WalletPayoutRequest;
use App\Http\Requests\MainHttp\Withdrawal\WithdrawalHistoryRequest;
use App\Models\Profile;
use App\Models\AdminAuditLog;
use App\Models\Withdrawal;
use App\Models\WithdrawSystem;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class WithdrawalService
{

    public function getAllWithdrawSysts(Request $request){
        return WithdrawSystem::where('hidden', 0)->orderBy('order', 'asc')->get()->map(function($item){
            $item->id = $item->text;
            return $item;
        });
    }

    public function getAccData(GetAccountDataRequest $request){
        $admin = false;
        if($request->id && Helper::isAdmin()){
            $admin = true;
        }

        $id = $admin ? $request->id : Auth::user()->id;
        User::where('id', $id)->where('left_turnover', '<', 0)->update(['left_turnover' => 0]);
        $user = $admin ? User::where('id', $id)->firstOrFail() : Auth::user();
        $user->setAttribute(
            'withdrawal_verified',
            (bool) Profile::where('user_id', $id)->whereNotNull('user_verify_at')->exists()
        );
        return $user;
    }

    public function processOfPayout(WalletPayoutRequest $request)
    {
        return $this->createWalletPayout($request)['data'];
    }

    public function createWalletPayout(WalletPayoutRequest $request)
    {
        $admin = $request->filled('id') && Helper::isAdmin();
        if ($admin && config('custom.demo')) {
            return ['data' => ['success' => false, 'message' => __('locale.demo_error')], 'status' => 403];
        }

        $userId = $admin ? (int) $request->id : (int) Auth::id();
        $methodKey = $request->method;
        $method = config('withdrawal.methods.'.$methodKey);
        if (!$method) {
            return ['data' => ['success' => false, 'message' => 'Unsupported withdrawal method.'], 'status' => 422];
        }

        $amount = round((float) $request->amount, 2);
        $fee = round(($amount * (float) $method['fee_percent'] / 100) + (float) $method['fee_fixed'], 2);
        $netAmount = round($amount - $fee, 2);
        $exchangeRate = null;
        if ($methodKey === 'mpesa') {
            try {
                $exchangeRate = (float) app(ExchangeRateService::class)->getLatestUsdKesRate()->effective_rate;
            } catch (ExchangeRateUnavailableException $exception) {
                return ['data' => ['success' => false, 'message' => 'M-Pesa withdrawals are unavailable until a verified USD/KES rate is available.'], 'status' => 503];
            }
        }
        $payoutAmount = $methodKey === 'mpesa'
            ? round($netAmount * $exchangeRate, 2)
            : $netAmount;
        $payoutCurrency = $methodKey === 'mpesa' ? 'KES' : 'USDT';
        if ($fee < 0 || $netAmount <= 0 || $payoutAmount <= 0) {
            return ['data' => ['success' => false, 'message' => 'The configured withdrawal fee is invalid.'], 'status' => 422];
        }

        $destination = $methodKey === 'mpesa'
            ? $request->phone_number
            : $request->wallet_address;
        $result = DB::transaction(function () use ($userId, $request, $admin, $methodKey, $amount, $fee, $payoutAmount, $payoutCurrency, $exchangeRate, $destination) {
            $user = User::where('id', $userId)->lockForUpdate()->first();
            if (!$user) {
                return ['error' => 'The account for this withdrawal could not be found.'];
            }

            $existing = Withdrawal::where('request_key', $request->request_key)->first();
            if ($existing) {
                if ((int) $existing->user_id !== $userId) {
                    return ['error' => 'This withdrawal request key has already been used.'];
                }
                if ($existing->method !== $methodKey
                    || $existing->address !== $destination
                    || round((float) $existing->amount, 2) !== $amount) {
                    return ['error' => 'This request key belongs to a different withdrawal request.'];
                }
                return ['duplicate' => true, 'withdrawal' => $existing];
            }

            if ((float) $user->balance < $amount) {
                return ['error' => __('locale.withdraw_not_enough_money')];
            }
            if ((float) $user->left_turnover > 0) {
                return ['error' => __('locale.withdraw_before')];
            }

            $profile = Profile::where('user_id', $userId)->first();
            if (!$profile || !$profile->user_verify_at) {
                return ['error' => __('locale.withdraw_verify')];
            }

            $balanceBefore = round((float) $user->balance, 4);
            $user->balance = round($balanceBefore - $amount, 4);
            $user->save();

            $withdrawal = new Withdrawal();
            $withdrawal->user_id = $userId;
            $withdrawal->status = 0;
            $withdrawal->system_id = 0;
            $withdrawal->method = $methodKey;
            $withdrawal->address = $destination;
            $withdrawal->amount = $amount;
            $withdrawal->fee = $fee;
            $withdrawal->payout_amount = $payoutAmount;
            $withdrawal->payout_currency = $payoutCurrency;
            $withdrawal->payout_exchange_rate = $exchangeRate;
            $withdrawal->request_key = $request->request_key;
            $withdrawal->save();

            if ($admin) {
                AdminAuditLog::record('withdrawal.admin_requested', $request->reason, $user->id, $request->request_key, [
                    'withdrawal_id' => $withdrawal->id,
                    'amount' => $amount,
                    'fee' => $fee,
                    'payout_amount' => $payoutAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $user->balance,
                ]);
            }

            return ['withdrawal' => $withdrawal, 'balance' => $user->balance];
        });

        if (isset($result['error'])) {
            return ['data' => ['success' => false, 'message' => $result['error']], 'status' => 422];
        }
        if (isset($result['duplicate'])) {
            return ['data' => [
                'success' => true,
                'message' => 'This withdrawal request was already submitted.',
                'withdrawal_id' => $result['withdrawal']->id,
                'duplicate' => true,
            ], 'status' => 200];
        }

        $user = User::where('id', $userId)->firstOrFail();
        broadcast(new ChangeBalance($result['balance'], $user));

        return ['data' => [
            'success' => true,
            'message' => 'Withdrawal request submitted for admin review. No payout has been sent yet.',
            'withdrawal_id' => $result['withdrawal']->id,
            'amount' => number_format($amount, 2, '.', ''),
            'fee' => number_format($fee, 2, '.', ''),
            'payout_amount' => number_format($payoutAmount, 2, '.', ''),
            'payout_currency' => $payoutCurrency,
            'payout_exchange_rate' => $exchangeRate,
        ], 'status' => 200];
    }

    public function withdrawalHist(WithdrawalHistoryRequest $request)
    {
        $admin = false;
        if($request->id && Helper::isAdmin()){
            $admin = true;
        }
        $id = $admin ? $request->id : Auth::user()->id;
        $history = Withdrawal::where('user_id', $id)->with(['withdrawSystem'])->get();
        return Datatables::of($history)->make();
    }


}