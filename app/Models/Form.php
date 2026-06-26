<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'supervisor_id',
        'title',
        'description',
        'header_image_path',
        'color',
        'slug',
        'fields',
        'policy_text',
        'success_text',
        'is_public_report',
        'public_report_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            if (empty($form->public_report_token)) {
                $form->public_report_token = \Illuminate\Support\Str::random(12);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'is_public_report' => 'boolean',
        ];
    }

    /** @return BelongsTo<Supervisor, $this> */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    /** @return HasMany<FormResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(FormResponse::class);
    }
}
