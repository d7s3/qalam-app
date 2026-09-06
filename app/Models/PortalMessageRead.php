<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Read once is read: the announcement does not come back. */
class PortalMessageRead extends Model
{
    protected $fillable = ['portal_message_id', 'user_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
