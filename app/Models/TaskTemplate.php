<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of tasks the academy raises together.
 *
 * Opening a term is the same twelve tasks with the same twelve owners every
 * time, and typing them out each term is how three of them come to be forgotten
 * in the term somebody was busy.
 *
 * Dates are written relative to the day the template is applied, so «three days
 * before» stays three days before whichever term it is.
 */
class TaskTemplate extends Model
{
    protected $fillable = ['name', 'description', 'created_by_type', 'created_by_id'];

    /** @return HasMany<TaskTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(TaskTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
