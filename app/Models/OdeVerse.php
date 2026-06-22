<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        static::deleted(function (OdeVerse $verse) {
            $verses = static::where('ode_id', $verse->ode_id)
                ->orderBy('verse_number')
                ->get();

            foreach ($verses as $index => $v) {
                $newNumber = $index + 1;
                if ($v->verse_number !== $newNumber) {
                    $v->update(['verse_number' => $newNumber]);
                }
            }
        });
    }

    public function ode()
    {
        return $this->belongsTo(Ode::class);
    }
}
