<?php

namespace App\Http\Controllers\Main;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionHistoryController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $deposits = DB::table('deposits')
            ->where('user_id', $userId)
            ->select('id', 'status')
            ->selectRaw("'deposit' AS type")
            ->selectRaw('COALESCE(usd_amount, amount) AS amount')
            ->selectRaw('kes_amount AS payout_amount')
            ->selectRaw("CASE WHEN kes_amount IS NULL THEN 'USD' ELSE 'KES' END AS currency")
            ->addSelect('reference', 'created_at');

        $withdrawals = DB::table('withdrawals')
            ->where('user_id', $userId)
            ->select('id', 'status')
            ->selectRaw("'withdrawal' AS type")
            ->selectRaw('amount AS amount')
            ->selectRaw('payout_amount AS payout_amount')
            ->selectRaw("COALESCE(payout_currency, 'USD') AS currency")
            ->selectRaw('transaction_reference AS reference')
            ->addSelect('created_at');

        $query = DB::query()
            ->fromSub($deposits->unionAll($withdrawals), 'transactions')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return response()->json($query->paginate(25));
    }
}
