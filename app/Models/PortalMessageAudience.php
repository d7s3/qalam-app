<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An office, or one person in it, that a portal message was addressed to. */
class PortalMessageAudience extends Model
{
    protected $fillable = ['portal_message_id', 'role_key', 'user_id'];

    /** @return BelongsTo<PortalMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(PortalMessage::class, 'portal_message_id');
    }
}
