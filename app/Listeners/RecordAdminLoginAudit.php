<?php

namespace App\Listeners;

use App\Models\AdminAuditLog;
use App\Models\Role;
use App\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Schema;

class RecordAdminLoginAudit
{
    public function handle($event)
    {
        if (!Schema::hasTable('admin_audit_logs')) {
            return;
        }

        if ($event instanceof Login) {
            $user = $event->user;
            if ($user instanceof User && Role::where('user_id', $user->id)->where('role', 1)->exists()) {
                $this->record('security.admin_login_succeeded', $user->id, $user->email);
            }
            return;
        }

        if ($event instanceof Failed) {
            $email = isset($event->credentials['email']) ? (string) $event->credentials['email'] : null;
            if ($email && User::withTrashed()->where('email', $email)->exists()) {
                $user = User::withTrashed()->where('email', $email)->first();
                if (Role::where('user_id', $user->id)->where('role', 1)->exists()) {
                    $this->record('security.admin_login_failed', $user->id, $email);
                }
            }
        }
    }

    private function record($action, $userId, $email)
    {
        $entry = new AdminAuditLog();
        $entry->actor_user_id = $userId;
        $entry->affected_user_id = $userId;
        $entry->action = $action;
        $entry->reason = 'Admin authentication event.';
        $entry->metadata = ['email' => $email];
        $entry->ip_address = request()->ip();
        $entry->save();
    }
}
