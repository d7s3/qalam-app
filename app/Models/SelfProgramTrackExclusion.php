<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A field of the programme set aside, for a while or for good.
 *
 * See the migration for why only the setting aside is stored.
 */
class SelfProgramTrackExclusion extends Model
{
    protected $fillable = [
        'self_program_track_id',
        'stage_id',
        'starts_on',
        'ends_on',
        'reason',
        'created_by_id',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** @return BelongsTo<SelfProgramTrack, $this> */
    public function track(): BelongsTo
    {
        return $this->belongsTo(SelfProgramTrack::class, 'self_program_track_id');
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
