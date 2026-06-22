<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OdeVerse extends Model
{
    protected $fillable = [
        'ode_id',
        'verse_number',
        'sadr',
        'ajuz',
    ];

    protected static function booted(): void
    {
        static::saving(function (OdeVerse $verse) {
            if ($verse->isDirty('verse_number')) {
                if ($verse->exists) {
                    DB::table('ode_verses')
                        ->where('id', $verse->id)
                        ->update(['verse_number' => 0]);
                }

                $conflictVerses = static::where('ode_id', $verse->ode_id)
                    ->where('id', '!=', $verse->id)
                    ->where('verse_number', '>=', $verse->verse_number)
                    ->orderBy('verse_number', 'desc')
                    ->get();

                foreach ($conflictVerses as $cv) {
                    DB::table('ode_verses')
                        ->where('id', $cv->id)
                        ->increment('verse_number');
                }
            }
        });

        static::deleted(function (OdeVerse $verse) {
            $verses = static::where('ode_id', $verse->ode_id)
                ->orderBy('verse_number')
                ->get();

            foreach ($verses as $index => $v) {
                $newNumber = $index + 1;
                if ($v->verse_number !== $newNumber) {
                    DB::table('ode_verses')
                        ->where('id', $v->id)
                        ->update(['verse_number' => $newNumber]);
                }
            }
        });
    }

    public function ode()
    {
        return $this->belongsTo(Ode::class);
    }
}
