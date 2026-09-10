<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What happened to a task, written by the application rather than by a person.
 *
 * A task that slipped three times is a different thing from a task that was
 * late once, and the difference is invisible in a status field — which only
 * ever holds where the task is now. This holds where it has been.
 */
class TaskActivity extends Model
{
    public const CREATED = 'created';

    public const STAGE = 'stage';

    public const STATUS = 'status';

    public const ASSIGNEE = 'assignee';

    public const DUE_DATE = 'due_date';

    public const ESCALATED = 'escalated';

    protected $fillable = ['task_id', 'actor_type', 'actor_id', 'action', 'from_value', 'to_value'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** The line as it is read in the record. */
    public function say(): string
    {
        $who = ($this->actor_id ? User::find($this->actor_id)?->name : null) ?? __('النظام');

        return match ($this->action) {
            self::CREATED => __(':who أنشأها', ['who' => $who]),
            self::STAGE => __(':who نقلها من :from إلى :to', ['who' => $who, 'from' => $this->from_value, 'to' => $this->to_value]),
            self::STATUS => __(':who غيّر حالتها إلى :to', ['who' => $who, 'to' => $this->to_value]),
            self::ASSIGNEE => __(':who أسندها إلى :to', ['who' => $who, 'to' => $this->to_value]),
            self::DUE_DATE => __(':who نقل موعدها من :from إلى :to', ['who' => $who, 'from' => $this->from_value ?: '—', 'to' => $this->to_value ?: '—']),
            self::ESCALATED => __('تجاوزت موعدها، فأُبلغ :to', ['to' => $this->to_value]),
            default => $this->action,
        };
    }
}
