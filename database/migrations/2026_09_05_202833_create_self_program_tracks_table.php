<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The fields the self programme is made of, as rows.
     *
     * They were five constants in code, and the reasoning written beside them
     * was that the programme is *defined* as those five. The academy has since
     * decided otherwise: a month may run on three of them, a sixth may be
     * wanted, and one may be set aside for a term without being lost.
     *
     * The five keep their keys, so every week ever written still reads. What
     * changes is that the list is no longer a thing only a release can alter.
     *
     * @var array<int, array<string, mixed>>
     */
    private const SEED = [
        ['key' => 'quran_wird', 'label' => 'الورد القرآني', 'default_unit' => 'صفحة', 'fixed_unit' => 'صفحة', 'icon' => 'book-open'],
        ['key' => 'maqrou', 'label' => 'المقروء', 'default_unit' => 'صفحة', 'fixed_unit' => null, 'icon' => 'document-text'],
        ['key' => 'masmou', 'label' => 'المسموع', 'default_unit' => 'درس', 'fixed_unit' => null, 'icon' => 'speaker-wave'],
        ['key' => 'tahdheer', 'label' => 'التحضير', 'default_unit' => 'درس', 'fixed_unit' => null, 'icon' => 'pencil-square'],
        ['key' => 'mahfoudh', 'label' => 'المحفوظ', 'default_unit' => 'حديث', 'fixed_unit' => null, 'icon' => 'bookmark'],
    ];

    public function up(): void
    {
        Schema::create('self_program_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('default_unit')->default('وحدة');

            // Set only where the arithmetic depends on it: the wird is written
            // in mushaf pages by the recitation bridge, so no supervisor may
            // choose another unit for it.
            $table->string('fixed_unit')->nullable();

            $table->string('icon')->default('sparkles');

            // A field the programme was defined by. It may be set aside for a
            // period, but not deleted out from under the weeks that used it.
            $table->boolean('is_system')->default(false);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach (self::SEED as $index => $row) {
            DB::table('self_program_tracks')->insert($row + [
                'is_system' => true,
                'sort_order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('self_program_tracks');
    }
};
