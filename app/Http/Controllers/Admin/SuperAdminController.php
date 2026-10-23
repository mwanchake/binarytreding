<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAccessService;
use App\Services\Admin\SuperAdminService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class SuperAdminController extends Controller
{
    public function access(Request $request, AdminAccessService $access)
    {
        return response()->json([
            'permissions' => $access->permissions($request->user()),
            'is_super_admin' => $access->isSuperAdmin($request->user()),
            'permission_catalog' => AdminAccessService::PERMISSIONS,
        ]);
    }

    public function resetUserPassword($id)
    {
        $user = User::findOrFail($id);
        $status = Password::sendResetLink(['email' => $user->email]);
        \App\Models\AdminAuditLog::record('user.password_reset_requested', 'Administrator requested a secure password reset email.', $user->id);
        return response()->json([
            'success' => $status === Password::RESET_LINK_SENT,
            'message' => $status === Password::RESET_LINK_SENT
                ? 'Password reset email requested.'
                : 'Password reset email could not be sent. Check mail configuration and retry.',
        ], $status === Password::RESET_LINK_SENT ? 200 : 503);
    }

    public function dashboard(Request $request, SuperAdminService $service)
    {
        return response()->json($service->dashboard($request));
    }

    public function users(Request $request, SuperAdminService $service)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,blocked,deleted'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        return response()->json($service->users($request));
    }

    public function userDetails($id, SuperAdminService $service)
    {
        return response()->json($service->userDetails($id));
    }

    public function createUser(Request $request, SuperAdminService $service)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $user = $service->createUser($data);
        $resetStatus = Password::sendResetLink(['email' => $user->email]);
        return response()->json([
            'success' => true,
            'message' => 'User created. Password reset email status: '.$resetStatus.'.',
            'user_id' => $user->id,
            'password_reset_requested' => $resetStatus === Password::RESET_LINK_SENT,
        ], 201);
    }

    public function updateUser($id, Request $request, SuperAdminService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email,'.$id],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        return response()->json(['success' => true, 'user' => $service->updateUser($id, $data)]);
    }

    public function setUserStatus($id, Request $request, SuperAdminService $service)
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,blocked'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        return response()->json($service->setUserStatus($id, $data['status'], $data['reason']));
    }

    public function deleteUser($id, Request $request, SuperAdminService $service)
    {
        $data = $request->validate([
            'confirmation' => ['required', 'accepted'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        return response()->json($service->deleteUser($id, $data['reason']));
    }

    public function adminAccounts(SuperAdminService $service)
    {
        return response()->json([
            'accounts' => $service->adminAccounts(),
            'roles' => ['super_admin', 'admin', 'finance_admin', 'support_admin'],
            'permissions' => AdminAccessService::PERMISSIONS,
        ]);
    }

    public function assignAdminRole($id, Request $request, SuperAdminService $service)
    {
        $data = $request->validate([
            'role' => ['required', 'in:super_admin,admin,finance_admin,support_admin'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'in:'.implode(',', AdminAccessService::PERMISSIONS)],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        return response()->json($service->assignAdminRole(
            $id,
            $data['role'],
            $data['permissions'] ?? [],
            $data['reason']
        ));
    }

    public function auditLogs(Request $request, SuperAdminService $service)
    {
        $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        return response()->json($service->auditLogs($request));
    }

    public function exportFinancialReport(Request $request, SuperAdminService $service)
    {
        $request->validate([
            'type' => ['required', 'in:deposits,withdrawals,wallet_adjustments'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        return $service->exportFinancialReport($request);
    }

    public function adjustWallet($id, Request $request, SuperAdminService $service)
    {
        $data = $request->validate([
            'action' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d+(\.\d{1,4})?$/'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'reference' => ['required', 'uuid'],
            'confirmation' => ['required', 'accepted'],
        ]);
        $data['user_id'] = (int) $id;
        return response()->json($service->adjustWallet($data));
    }
}
