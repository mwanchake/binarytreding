<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;

class SettingsService
{
    public function getAllSettingsServ(Request $request)
    {
        $keys = [];
        if(config('custom.demo')){
            foreach(DotenvEditor::getKeys() as $key => $value){
                $value['value'] = __('locale.demo_error');
                $keys[$key] = $value;
            }
        } else {
            $keys = DotenvEditor::getKeys();
        }
        foreach ($keys as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                unset($keys[$key]);
            }
        }
        return $keys;
    }

    public function saveServ(Request $request)
    {
        if(config('custom.demo')){
            return (['success' => false, 'message' => __('locale.demo_error')]);
        }
        DotenvEditor::autoBackup(false);
        $result = null;
        parse_str($request->data, $result);
        $availableKeys = DotenvEditor::getKeys();
        $changedKeys = [];
        foreach ($result as $key => $value) {
            if (!array_key_exists($key, $availableKeys) || $this->isSensitiveKey($key) || !is_scalar($value)) {
                continue;
            }
            DotenvEditor::setKey($key, $value, '', false);
            $changedKeys[] = $key;
        }
        if (!$changedKeys) {
            return ['success' => false, 'message' => 'No permitted settings were provided.'];
        }
        DotenvEditor::save();
        AdminAuditLog::record('configuration.updated', $request->reason, null, null, ['keys' => $changedKeys]);
        return (['success' => true, 'message' => __('locale.admin_settings_success')]);
    }

    private function isSensitiveKey($key)
    {
        return preg_match('/secret|password|token|private|credential|(^|_)key($|_)/i', $key) === 1;
    }
}
