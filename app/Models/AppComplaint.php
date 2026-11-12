<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A complaint about the platform, sent to the Telegram bot.
 *
 * Asked for by Faisal, 12 Nov 2026: a name, a role, and a screenshot with its
 * explanation or a clip of the problem. The row is made at the first question
 * and filled one answer at a time; it is a complaint once `step` is done.
 */
class AppComplaint extends Model
{
    use HasFactory;

    public const STEP_NAME = 'name';

    public const STEP_ROLE = 'role';

    public const STEP_MEDIA = 'media';

    public const STEP_DESCRIPTION = 'description';

    public const STEP_DONE = 'done';

    /** The roles a sender picks from, as the bot's buttons say them. */
    public const ROLES = ['طالب', 'معلم', 'مشرف', 'ولي أمر', 'إداري'];

    protected $fillable = [
        'chat_id', 'telegram_username', 'step', 'name', 'role', 'description',
        'media_type', 'media_file_id', 'media_message_id', 'media_path',
        'user_id', 'analysis', 'status', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'analysis' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isSubmitted(): bool
    {
        return $this->step === self::STEP_DONE;
    }
}
