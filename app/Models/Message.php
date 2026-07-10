<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_type',
        'sender_id',
        'body',
    ];

    protected static function booted(): void
    {
        static::created(function (Message $message) {
            $message->conversation()->update(['last_message_at' => $message->created_at]);
        });
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return MorphTo<Model, $this> */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }
}
