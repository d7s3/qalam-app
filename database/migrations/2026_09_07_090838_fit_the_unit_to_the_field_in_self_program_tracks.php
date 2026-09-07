<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The unit a field is measured in, made to follow the field.
     *
     * The unit was free text beside a free number, so nothing stopped a
     * programme asking for two and a half hadiths, and nothing said how the
     * listening was to be reported — one supervisor wrote lessons, another
     * wrote minutes, and the two could not be added together.
     *
     * Each field now carries the units it may take. One choice means the unit
     * is settled and the form only shows it. Several means the author picks,
     * and the label beside each says what the choice means rather than what it
     * is called: التحضير is prepared «مسموعاً» or «مقروءاً», not «بالدقائق» or
     * «بالصفحات».
     *
     * Written as a map so a field may name a unit and say what choosing it
     * means in the same place.
     */
    private const CHOICES = [
        'quran_wird' => ['صفحة' => 'صفحات'],
        'maqrou' => ['صفحة' => 'صفحات'],
        'masmou' => ['دقيقة' => 'وقتاً'],
        'tahdheer' => ['دقيقة' => 'مسموعاً', 'صفحة' => 'مقروءاً'],
        'mahfoudh' => ['بيت' => 'أبياتاً', 'حديث' => 'أحاديث', 'صفحة' => 'صفحات'],
    ];

    public function up(): void
    {
        Schema::table('self_program_tracks', function (Blueprint $table) {
            $table->json('unit_choices')->nullable();
        });

        foreach (self::CHOICES as $key => $choices) {
            DB::table('self_program_tracks')->where('key', $key)->update([
                'unit_choices' => json_encode($choices, JSON_UNESCAPED_UNICODE),
                // The first choice is what a new week is written in; a field
                // with one choice has its unit settled by that alone.
                'default_unit' => array_key_first($choices),
                'fixed_unit' => count($choices) === 1 ? array_key_first($choices) : null,
            ]);
        }

        // Weeks already written keep the unit they were written in. «درس» is
        // not in the vocabulary, but converting lessons into minutes would be
        // inventing a number nobody wrote, so what is there is left to be read
        // and only what is written from now on is held to the vocabulary.
    }

    public function down(): void
    {
        Schema::table('self_program_tracks', function (Blueprint $table) {
            $table->dropColumn('unit_choices');
        });
    }
};
