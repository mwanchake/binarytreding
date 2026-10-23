<?php

namespace App\Services\MainHttp;

use App\Events\ChangeBalance;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MainHttp\Promocode\CheckPromocodeRequest;
use App\Http\Requests\MainHttp\Promocode\DiscardBonusRequest;
use App\Http\Requests\MainHttp\Promocode\PromocodeHistoryRequest;
use App\Models\Deposit;
use App\Models\AdminAuditLog;
use App\Models\AdminWalletAdjustment;
use App\Models\LatestOrder;
use App\Models\OpenOrders;
use App\Models\Promocode;
use App\Models\PromocodeHistory;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;

class PromocodeService
{
    public function getAvailablePromocodesServ(Request $request){
        return Promocode::where('active_from', '<', Carbon::now())
            ->where('active_to', '>', Carbon::now())
            ->where('public_code', 1)
            ->get();
    }

    public function checkPromocodeServ(CheckPromocodeRequest $request){

        $admin = false;
        if($request->id && Helper::isAdmin()){
            if(config('custom.demo')){
                return (['success' => false, 'message' => __('locale.demo_error')]);
            }
            $admin = true;
        }
        $id = $admin ? $request->id : Auth::user()->id;
        $promocode = Promocode::where('code', $request->code)->first();
        if(!$promocode){
            return (['data' => ['success' => false, 'message' => __('locale.promo_code_not_exist')], 'status' => 200]);
        }
        $active_from = Carbon::parse($promocode->active_from);
        $active_to = Carbon::parse($promocode->active_to);
        if(Carbon::now() < $active_from or Carbon::now() > $active_to){
            return (['data' => ['success' => false, 'message' => __('locale.promo_code_validity_expired')], 'status' => 200]);
        }
        $deposits = Deposit::where('user_id', $id)->where('status', 1)->count() + LatestOrder::where('user_id', $id)->count() + PromocodeHistory::where('user_id', $id)->whereHas('promocode', function($query){ $query->where('for_new', 1); })->count();
        if($promocode->for_new and $deposits){
            return (['data' => ['success' => false, 'message' => __('locale.promo_code_only_for_new')], 'status' => 200]);
        }
        $promocode_history = PromocodeHistory::where('user_id', $id)->where('promocode_id', $promocode->id)->count();
        if($promocode->attempts && $promocode_history >= $promocode->attempts){
            return (['data' => ['success' => false, 'message' => __('locale.promo_code_used_max_times')], 'status' => 200]);
        }
        if($promocode->type == 1) {
            $turnover = $promocode->bonus_size * $promocode->turnover;
            $model = new PromocodeHistory;
            $model->user_id = $id;
            $model->promocode_id = $promocode->id;
            $model->save();
            User::where('id', $id)
                ->update([
                    'bonus' => DB::raw("bonus+$promocode->bonus_size"),
                    'all_turnover' => DB::raw("all_turnover+$turnover"),
                    'left_turnover' => DB::raw("left_turnover+$turnover"),
                    'balance' => DB::raw("balance+$promocode->bonus_size")
                ]);
            $user = $admin ? User::where('id', $id)->first() : Auth::user();
            broadcast(new ChangeBalance($user->balance + $promocode->bonus_size, $user));
            return (['data' => ['success' => true, 'message' => __('locale.promo_code_without_deposit_activated')], 'status' => 200]);
        } elseif ($promocode->type == 2){
            return (['data' => ['success' => true, 'message' => __('locale.promo_code_active'), 'data' => $promocode], 'status' => 200]);
        }
    }

    public function getDepositPromocodesServ(Request $request){
        return Promocode::where('public_code', 2)->orderBy('bonus_size', 'desc')->get();
    }

    public function promocodeHistoryServ(PromocodeHistoryRequest $request)
    {
        $admin = false;
        if($request->id && Helper::isAdmin()){
            $admin = true;
        }
        $id = $admin ? $request->id : Auth::user()->id;
        $history = PromocodeHistory::where('user_id', $id)->with('promocode')->get();
        return Datatables::of($history)->make();
    }

    public function discardBonusServ(DiscardBonusRequest $request){
        $admin = (bool) $request->id && Helper::isAdmin();
        if($admin){
            if(config('custom.demo')){
                return (['success' => false, 'message' => __('locale.demo_error')]);
            }
        }
        $id = $admin ? $request->id : Auth::user()->id;
        $result = DB::transaction(function () use ($request, $admin, $id) {
            $user = User::whereKey($id)->lockForUpdate()->firstOrFail();
            if(OpenOrders::where('user_id', $id)->count()){
                return ['data' => ['success' => false, 'message' => __('locale.promo_code_you_have_open_orders')], 'status' => 200];
            }
            if($user->left_turnover == 0 or $user->all_turnover <= 0){
                return ['data' => ['success' => false, 'message' => __('locale.promo_code_you_dont_have_bonuses')], 'status' => 200];
            }

            $before = round((float) $user->balance, 4);
            $percentToPayout = 1 - ($user->left_turnover / $user->all_turnover);
            $newBalance = max(0, round($before + ($user->bonus * $percentToPayout) - $user->bonus, 4));
            $amount = round(abs($newBalance - $before), 4);
            $reference = (string) Str::uuid();
            $user->bonus = 0;
            $user->all_turnover = 0;
            $user->left_turnover = 0;
            $user->balance = $newBalance;
            $user->save();

            if ($amount > 0) {
                $adjustment = new AdminWalletAdjustment();
                $adjustment->reference = $reference;
                $adjustment->user_id = $user->id;
                $adjustment->actor_user_id = Auth::id();
                $adjustment->action = $newBalance >= $before ? 'credit' : 'debit';
                $adjustment->amount = $amount;
                $adjustment->balance_before = $before;
                $adjustment->balance_after = $newBalance;
                $adjustment->reason = $admin ? $request->reason : 'User-initiated bonus cancellation.';
                $adjustment->save();
            }

            if ($admin) {
                AdminAuditLog::record('wallet.bonus_discarded', $request->reason, $user->id, $reference, [
                    'balance_before' => $before,
                    'balance_after' => $newBalance,
                    'amount' => $amount,
                ]);
            }

            return [
                'data' => ['success' => true, 'message' => __('locale.promo_code_bonus_decline')],
                'status' => 200,
                'balance' => $newBalance,
                'user' => $user,
            ];
        });
        if (isset($result['user'])) {
            broadcast(new ChangeBalance($result['balance'], $result['user']));
        }
        unset($result['balance'], $result['user']);
        return $result;
    }
}
