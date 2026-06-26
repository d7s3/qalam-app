<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OdePath extends Model
{
    use HasFactory;

    protected $table = 'ode_paths';

    protected $fillable = [
        'ode_id',
        'name',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /** @return BelongsTo<Ode, $this> */
    public function ode(): BelongsTo
    {
        return $this->belongsTo(Ode::class, 'ode_id');
    }

    /** @return HasMany<OdePathDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(OdePathDay::class, 'ode_path_id')->orderBy('day_number', 'asc');
    }

    /** @return HasMany<StudentOdePlan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(StudentOdePlan::class, 'ode_path_id');
    }
}
