<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class HadithLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'hadith_id',
        'line_number',
        'text',
    ];

    protected static function booted(): void
    {
        static::saving(function (HadithLine $line) {
            if ($line->isDirty('line_number')) {
                if ($line->exists) {
                    DB::table('hadith_lines')
                        ->where('id', $line->id)
                        ->update(['line_number' => 0]);
                }

                $conflictLines = static::where('hadith_id', $line->hadith_id)
                    ->where('id', '!=', $line->id)
                    ->where('line_number', '>=', $line->line_number)
                    ->orderBy('line_number', 'desc')
                    ->get();

                foreach ($conflictLines as $cl) {
                    DB::table('hadith_lines')
                        ->where('id', $cl->id)
                        ->increment('line_number');
                }
            }
        });

        static::saved(function (HadithLine $line) {
            static::resequence($line->hadith_id);
        });

        static::deleted(function (HadithLine $line) {
            static::resequence($line->hadith_id);
        });
    }

    public static function resequence(int $hadithId): void
    {
        $lines = static::where('hadith_id', $hadithId)
            ->orderBy('line_number')
            ->orderBy('id')
            ->get();

        foreach ($lines as $index => $l) {
            $newNumber = $index + 1;
            if ($l->line_number !== $newNumber) {
                DB::table('hadith_lines')
                    ->where('id', $l->id)
                    ->update(['line_number' => $newNumber]);
            }
        }
    }

    /** @return BelongsTo<Hadith, $this> */
    public function hadith(): BelongsTo
    {
        return $this->belongsTo(Hadith::class);
    }
}
