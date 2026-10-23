<?php

namespace App\Services\Admin;

use App\Models\AdminAccount;
use App\Models\Role;
use App\User;
use Illuminate\Routing\Route;

class AdminAccessService
{
    const PERMISSIONS = [
        'dashboard.view',
        'users.view',
        'users.manage',
        'finance.deposits.view',
        'finance.deposits.manage',
        'finance.withdrawals.view',
        'finance.withdrawals.manage',
        'finance.wallet.view',
        'finance.wallet.adjust',
        'trading.view',
        'trading.manage',
        'referrals.view',
        'referrals.manage',
        'config.manage',
        'audit.view',
        'admin.manage',
        'reports.export',
    ];

    public function isAdmin(User $user)
    {
        return Role::where('user_id', $user->id)->where('role', 1)->exists();
    }

    public function isSuperAdmin(User $user)
    {
        $account = AdminAccount::where('user_id', $user->id)->first();
        return !$account || $account->role_name === 'super_admin';
    }

    public function permissions(User $user)
    {
        if ($this->isSuperAdmin($user)) {
            return self::PERMISSIONS;
        }

        $account = AdminAccount::where('user_id', $user->id)->first();
        return $account ? array_values(array_intersect($account->permissions ?: [], self::PERMISSIONS)) : [];
    }

    public function can(User $user, $permission)
    {
        return $this->isAdmin($user)
            && ($this->isSuperAdmin($user) || in_array($permission, $this->permissions($user), true));
    }

    public function canAccessPanel(User $user)
    {
        return $this->isAdmin($user)
            && ($this->isSuperAdmin($user) || count($this->permissions($user)) > 0);
    }

    public function requiredPermission(Route $route)
    {
        $action = $route->getActionName();
        $action = preg_replace('/^.*?Controllers\\\\/', '', $action);
        $permissions = [
            'Spa\\SpaController@admin' => 'admin.panel',
            'Admin\\SuperAdminController@access' => 'admin.panel',
            'Admin\\SuperAdminController@dashboard' => 'dashboard.view',
            'Admin\\SuperAdminController@users' => 'users.view',
            'Admin\\SuperAdminController@userDetails' => 'users.view',
            'Admin\\SuperAdminController@createUser' => 'users.manage',
            'Admin\\SuperAdminController@updateUser' => 'users.manage',
            'Admin\\SuperAdminController@resetUserPassword' => 'users.manage',
            'Admin\\SuperAdminController@setUserStatus' => 'users.manage',
            'Admin\\SuperAdminController@deleteUser' => 'users.manage',
            'Admin\\SuperAdminController@adminAccounts' => 'admin.super_admin',
            'Admin\\SuperAdminController@assignAdminRole' => 'admin.super_admin',
            'Admin\\SuperAdminController@auditLogs' => 'audit.view',
            'Admin\\SuperAdminController@adjustWallet' => 'finance.wallet.adjust',
            'Admin\\SuperAdminController@exportFinancialReport' => 'reports.export',
            'Admin\\StatisticsController@getShortStatistics' => 'dashboard.view',
            'Admin\\StatisticsController@getAllUsers' => 'users.view',
            'Admin\\StatisticsController@getDailyStat' => 'dashboard.view',
            'Admin\\UserController@getUser' => 'users.view',
            'Admin\\UserController@updateEmail' => 'users.manage',
            'Admin\\UserController@updatePhone' => 'users.manage',
            'Admin\\UserController@updatePassword' => 'users.manage',
            'Admin\\VerifyController@listRequestVerify' => 'users.view',
            'Admin\\VerifyController@verifyAccount' => 'users.manage',
            'Admin\\VerifyController@unVerifyAccount' => 'users.manage',
            'Admin\\VerifyController@checkDocument' => 'users.manage',
            'Admin\\CheatController@getAllCheaters' => 'users.view',
            'Admin\\ControlController@getControlInfo' => 'users.view',
            'Admin\\ControlController@getUserBan' => 'users.view',
            'Admin\\ControlController@banAction' => 'users.manage',
            'Admin\\ControlController@changeBalance' => 'finance.wallet.adjust',
            'Admin\\DepositController@allDeposits' => 'finance.deposits.view',
            'Admin\\WithdrawalController@allWithdrawals' => 'finance.withdrawals.view',
            'Admin\\WithdrawalController@processWithdrawal' => 'finance.withdrawals.manage',
            'Admin\\DepositController@allDepositSystems' => 'config.manage',
            'Admin\\DepositController@editDepositSystem' => 'config.manage',
            'Admin\\DepositController@removeDepositSystem' => 'config.manage',
            'Admin\\DepositController@createDepositSystem' => 'config.manage',
            'Admin\\SettingsController@getAllSettings' => 'config.manage',
            'Admin\\SettingsController@save' => 'config.manage',
            'Admin\\WithdrawalController@allWithdrawalSystems' => 'config.manage',
            'Admin\\WithdrawalController@editWithdrawSystem' => 'config.manage',
            'Admin\\WithdrawalController@removeWithdrawSystem' => 'config.manage',
            'Admin\\WithdrawalController@createWithdrawSystem' => 'config.manage',
            'Admin\\SymbolController@getSymbol' => 'trading.view',
            'Admin\\SymbolController@allSymbols' => 'trading.view',
            'Admin\\SymbolController@saveSymbol' => 'trading.manage',
            'Admin\\SymbolController@removeSymbol' => 'trading.manage',
            'Admin\\SymbolController@createSymbol' => 'trading.manage',
            'Admin\\TradeHistoryController@getHistory' => 'trading.view',
            'Admin\\TradeHistoryController@getHistoryDemo' => 'trading.view',
            'Admin\\PartnerController@listRequestPartner' => 'referrals.view',
            'Admin\\PartnerController@approvePartner' => 'referrals.manage',
            'Admin\\PartnerController@discardPartner' => 'referrals.manage',
            'Admin\\ContestController@allContests' => 'trading.view',
            'Admin\\ContestController@loadContestData' => 'trading.view',
            'Admin\\ContestController@contestUsers' => 'trading.view',
            'Admin\\ContestController@getContestTradingData' => 'trading.view',
            'Admin\\ContestController@getContestDailyStat' => 'trading.view',
            'Admin\\ContestController@getContestUserData' => 'trading.view',
            'Admin\\ContestController@createContest' => 'trading.manage',
            'Admin\\ContestController@updateBalance' => 'trading.manage',
            'Admin\\ContestController@updateBan' => 'trading.manage',
            'Admin\\PromocodeController@getAllPromocode' => 'config.manage',
            'Admin\\PromocodeController@loadPromocode' => 'config.manage',
            'Admin\\PromocodeController@createPromocode' => 'config.manage',
            'Admin\\PromocodeController@savePromocode' => 'config.manage',
            'Admin\\PromocodeController@removePromocode' => 'config.manage',
            'Main\\WithdrawalController@getAccountData' => 'users.view',
            'Main\\WithdrawalController@withdrawalHistory' => 'finance.withdrawals.view',
            'Main\\WithdrawalController@processPayout' => 'finance.withdrawals.manage',
            'Main\\FileController@showImage' => 'users.view',
            'Main\\DepositController@depositHistory' => 'finance.deposits.view',
            'Main\\TradingController@tradingHistory' => 'trading.view',
            'Main\\TradingController@demoTradingHistory' => 'trading.view',
            'Main\\ReferralController@getUserReferralInfo' => 'referrals.view',
            'Main\\ReferralController@getUserReferrals' => 'referrals.view',
            'Main\\PromocodeController@promocodeHistory' => 'users.view',
            'Main\\PromocodeController@discardBonus' => 'finance.wallet.adjust',
            'Main\\ProfileController@loadAllProfileData' => 'users.view',
            'Main\\ProfileController@changeGeneralData' => 'users.manage',
            'Main\\ProfileController@changeMainData' => 'users.manage',
            'Main\\ContestController@getAllContests' => 'trading.view',
            'Main\\ContestController@getContestInfo' => 'trading.view',
            'Main\\ContestController@getWinnersForContest' => 'trading.view',
            'Main\\ContestController@getUserPlace' => 'trading.view',
            'Main\\ContestController@getHistory' => 'trading.view',
        ];

        if (isset($permissions[$action])) {
            return $permissions[$action];
        }

        if (strpos($action, 'Admin\\') !== false) {
            return 'admin.super_admin';
        }

        return 'admin.super_admin';
    }
}
