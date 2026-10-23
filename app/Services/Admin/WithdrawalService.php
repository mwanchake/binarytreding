<?php

namespace App\Services\Admin;

use App\Events\ChangeBalance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Withdrawal\CreateWithdrawSystemRequest;
use App\Http\Requests\Admin\Withdrawal\EditWithdrawSystemRequest;
use App\Http\Requests\Admin\Withdrawal\ProcessWithdrawalRequest;
use App\Http\Requests\Admin\Withdrawal\RemoveWithdrawSystemRequest;
use App\Models\Withdrawal;
use App\Models\WithdrawSystem;
use App\Models\AdminAuditLog;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Ixudra\Curl\Facades\Curl;
use Yajra\DataTables\DataTables;

class WithdrawalService
{
    public function processWithdrawalServ(ProcessWithdrawalRequest $request)
    {
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }
        $result = DB::transaction(function () use ($request) {
            $withdrawal = Withdrawal::where('id', $request->id)->lockForUpdate()->firstOrFail();
            if (!in_array((int) $withdrawal->status, [0, 3], true)) {
                return ['error' => __('locale.admin_withdraw_already_processed')];
            }

            $targetStatus = (int) $request->status;
            if ($targetStatus === 3) {
                if ((int) $withdrawal->status !== 0) {
                    return ['error' => 'Only pending withdrawals can be moved to processing.'];
                }
                $withdrawal->status = 3;
                $withdrawal->message = $request->comment;
                $withdrawal->save();
                AdminAuditLog::record('withdrawal.processing', $request->comment, $withdrawal->user_id, null, [
                    'withdrawal_id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                ]);

                return ['withdrawal' => $withdrawal, 'message' => 'Withdrawal moved to processing. No payment was sent.'];
            }

            if ($targetStatus === 1) {
                if ((int) $withdrawal->status !== 3) {
                    return ['error' => 'Move the withdrawal to processing before recording a completed payout.'];
                }
                $reference = trim((string) $request->transaction_reference);
                if ($withdrawal->method === 'binance_usdt_bep20'
                    && !preg_match('/^0x[a-fA-F0-9]{64}$/', $reference)) {
                    return ['error' => 'Enter the verified BNB Smart Chain transaction hash.'];
                }
                if ($withdrawal->method === 'mpesa'
                    && !preg_match('/^[A-Za-z0-9]{6,32}$/', $reference)) {
                    return ['error' => 'Enter the M-Pesa payout receipt reference.'];
                }
                if (Withdrawal::where('transaction_reference', $reference)
                    ->where('id', '<>', $withdrawal->id)
                    ->exists()) {
                    return ['error' => 'That payout reference is already recorded.'];
                }
                $withdrawal->status = 1;
                $withdrawal->transaction_reference = $reference;
                $withdrawal->message = $request->comment;
                $withdrawal->save();
                AdminAuditLog::record('withdrawal.completed', 'Payout marked complete after administrator verification.', $withdrawal->user_id, $reference, [
                    'withdrawal_id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                    'payout_amount' => $withdrawal->payout_amount,
                    'payout_currency' => $withdrawal->payout_currency,
                ]);

                return ['withdrawal' => $withdrawal, 'message' => 'Payout recorded as completed.'];
            }

            if (!in_array($targetStatus, [2, 4], true)) {
                return ['error' => 'Unsupported withdrawal status.'];
            }

            $user = User::where('id', $withdrawal->user_id)->lockForUpdate()->firstOrFail();
            $user->balance = round((float) $user->balance + (float) $withdrawal->amount, 2);
            $user->save();

            $withdrawal->status = $targetStatus;
            $withdrawal->message = $request->comment;
            $withdrawal->save();
            AdminAuditLog::record(
                $targetStatus === 2 ? 'withdrawal.rejected' : 'withdrawal.failed',
                $request->comment,
                $withdrawal->user_id,
                null,
                ['withdrawal_id' => $withdrawal->id, 'refunded_amount' => $withdrawal->amount]
            );

            return [
                'withdrawal' => $withdrawal,
                'user' => $user,
                'balance' => $user->balance,
                'message' => $targetStatus === 2 ? 'Withdrawal rejected and reserved funds refunded.' : 'Withdrawal failed and reserved funds refunded.',
            ];
        });

        if (isset($result['error'])) {
            return ['success' => false, 'message' => $result['error']];
        }
        if (isset($result['user'])) {
            broadcast(new ChangeBalance($result['balance'], $result['user']));
        }

        if ((int) $result['withdrawal']->status === 2) {
            $withdrawal = $result['withdrawal'];
            $message = $request->comment ? __('locale.admin_withdraw_cause').': '.$request->comment : __('locale.admin_withdraw_cause_decline');
            $mail_data = [
                'headline' => __('locale.admin_withdraw_declined'),
                'subtitle' =>  __('locale.admin_withdraw_not_implemented'),
                'text' => "<p>".__('locale.admin_withdraw_not_implemented_mail')." $message</p>",
                'image' => 'order-cancel.png',
                'button_link' => config('app.url').'/profile',
                'button_text' => __('locale.admin_withdraw_go_cabinet')
            ];
            try {
                Mail::send('mail.mail', $mail_data, function ($message) use ($request, $withdrawal) {
                    $message->from(config('mail.username'), config('app.name'));
                    $message->replyTo(config('mail.username'));
                    $message->subject(__('locale.admin_withdraw_declined'));
                    $message->to(User::where('id', $withdrawal->user_id)->first()->email);
                });
            } catch (\Throwable $ex) {
                Log::warning('Failed to send withdrawal rejection email.', [
                    'withdrawal_id' => $withdrawal->id,
                    'exception' => $ex,
                ]);
            }
        }

        return ['success' => true, 'message' => $result['message']];
    }

    public function allWithdrawalsServ(Request $request){
        $requests = Withdrawal::with(['user'])->get();
        return Datatables::of($requests)->make();
    }

    public function allWithdrawalSystemsServ(Request $request){
        return WithdrawSystem::orderBy('order', 'asc')->get();
    }

    public function editWithdrawSystemServ(EditWithdrawSystemRequest $request){
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }

        WithdrawSystem::where('text', $request->prevText)->update([
            'description' => $request->description,
            'text' => $request->text,
            'order' => $request->order,
            'hidden' => $request->active,
        ]);
        return (['success' => true, 'message' => __('locale.admin_withdraw_updated')]);
    }

    public function removeWithdrawSystemServ(RemoveWithdrawSystemRequest $request){
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }
        WithdrawSystem::where('text', $request->text)->delete();
        return (['success' => true, 'message' => __('locale.admin_withdraw_deleted')]);
    }

    public function createWithdrawSystemServ(CreateWithdrawSystemRequest $request){
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }

        $model = new WithdrawSystem();
        $model->description = $request->description;
        $model->text = $request->system;
        $model->order = $request->order;
        $model->hidden = $request->active;
        $model->save();
        return (['success' => true, 'message' => __('locale.admin_withdraw_created')]);
    }
}
