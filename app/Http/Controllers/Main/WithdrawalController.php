<?php

namespace App\Http\Controllers\Main;

use App\Events\ChangeBalance;
use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MainHttp\Withdrawal\GetAccountDataRequest;
use App\Http\Requests\MainHttp\Withdrawal\ProcessPayoutRequest;
use App\Http\Requests\MainHttp\Withdrawal\WithdrawalHistoryRequest;
use App\Http\Requests\MainHttp\Withdrawal\WalletPayoutRequest;
use App\Models\Profile;
use App\Models\Withdrawal;
use App\Models\WithdrawSystem;
use App\Services\MainHttp\WithdrawalService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class WithdrawalController extends Controller
{
    public function getAllWithdrawSystems(Request $request, WithdrawalService $withdrawalService)
    {
      return response()->json($withdrawalService->getAllWithdrawSysts($request));
    }

    public function getAccountData(GetAccountDataRequest $request, WithdrawalService $withdrawalService)
    {
        return response()->json($withdrawalService->getAccData($request));
    }

    public function processPayout(ProcessPayoutRequest $request, WithdrawalService $withdrawalService)
    {
        $result = $withdrawalService->createWalletPayout($request);
        return response()->json($result['data'], $result['status']);
    }

    public function walletOptions()
    {
        $exchangeRate = null;
        try {
            $exchangeRate = app(\App\Services\MainHttp\ExchangeRateService::class)
                ->getLatestUsdKesRate()->effective_rate;
        } catch (\App\Services\MainHttp\ExchangeRateUnavailableException $exception) {
            // M-Pesa requests remain unavailable until a verified USD/KES rate exists.
        }

        return response()->json([
            'minimum_amount' => config('withdrawal.minimum_amount'),
            'maximum_amount' => config('withdrawal.maximum_amount'),
            'usd_kes_rate' => $exchangeRate,
            'methods' => collect(config('withdrawal.methods'))->map(function ($method) {
                return [
                    'fee_percent' => $method['fee_percent'],
                    'fee_fixed' => $method['fee_fixed'],
                ];
            }),
        ]);
    }

    public function createWalletPayout(WalletPayoutRequest $request, WithdrawalService $withdrawalService)
    {
        $result = $withdrawalService->createWalletPayout($request);
        return response()->json($result['data'], $result['status']);
    }

  public function withdrawalHistory(WithdrawalHistoryRequest $request, WithdrawalService $withdrawalService)
  {
      return $withdrawalService->withdrawalHist($request);
  }
}
