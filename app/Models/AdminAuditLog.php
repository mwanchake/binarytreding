<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    protected $guarded = ['*'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo('App\User', 'actor_user_id')->withTrashed();
    }

    public function affectedUser()
    {
        return $this->belongsTo('App\User', 'affected_user_id')->withTrashed();
    }

    public static function record($action, $reason = null, $affectedUserId = null, $reference = null, array $metadata = [])
    {
        $entry = new static();
        $entry->actor_user_id = auth()->id();
        $entry->affected_user_id = $affectedUserId;
        $entry->action = $action;
        $entry->reason = $reason;
        $entry->reference = $reference;
        $entry->metadata = $metadata ?: null;
        $entry->ip_address = request()->ip();
        $entry->save();
        return $entry;
    }
}
