<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplateItem extends Model
{
    protected $fillable = [
        'task_template_id', 'title', 'description', 'task_category_id',
        'due_offset_days', 'assign_to_role', 'steps', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['steps' => 'array', 'due_offset_days' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<TaskTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }

    /** How the offset reads: «قبل ٣ أيام», «يوم البداية», «بعد أسبوع». */
    public function whenSaid(): string
    {
        return match (true) {
            $this->due_offset_days === 0 => __('يوم التطبيق'),
            $this->due_offset_days < 0 => __('قبله بـ :n يوماً', ['n' => abs($this->due_offset_days)]),
            default => __('بعده بـ :n يوماً', ['n' => $this->due_offset_days]),
        };
    }
}
