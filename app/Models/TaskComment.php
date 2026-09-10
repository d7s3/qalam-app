<?php

namespace App\Models;

use App\Support\RoleTitle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What somebody said about a task.
 *
 * Kept apart from the activity record because the two answer different
 * questions: a comment is a person's account of the work, and the activity is
 * the application's account of the clicks. A man may argue with the first.
 */
class TaskComment extends Model
{
    protected $fillable = ['task_id', 'author_type', 'author_id', 'body'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** The person who wrote it, whichever guard he holds. */
    public function author(): ?User
    {
        return User::find($this->author_id);
    }

    /** His name and the office he wrote from, for the line above the words. */
    public function authorLine(): string
    {
        $author = $this->author();

        return trim(($author?->name ?? __('غير معروف')).' · '.RoleTitle::for($author, $this->author_type));
    }
}
