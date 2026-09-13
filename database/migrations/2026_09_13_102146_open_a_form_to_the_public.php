<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A form anybody may answer, without an account.
     *
     * Every form until now was aimed at people already inside the academy — a
     * cohort, an office, a programme — which is right for asking teachers how a
     * term went and useless for the one thing an academy does before anybody is
     * inside it: taking applications.
     *
     * So a form may be opened. It keeps its audience for those who are signed
     * in and gains a link for those who are not, and the two do not interfere:
     * a form that is not opened behaves exactly as it did.
     *
     * The token is separate from the slug because a slug is chosen to be read
     * aloud and a token is chosen to be unguessable, and a link that closes
     * should not cost the form its name.
     */
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->boolean('is_public')->default(false);
            $table->string('public_token')->nullable()->unique();

            // What a stranger is told after answering, and what the page says
            // about the thing he is applying to.
            $table->text('public_intro')->nullable();

            // Closed on its own date, so nobody has to remember to close it the
            // morning after the seats ran out.
            $table->date('closes_on')->nullable();
        });

        Schema::table('form_responses', function (Blueprint $table) {
            // A stranger has no user row, so the answers carry his own contact
            // details and the academy reaches him by them until he has an
            // account of his own.
            $table->string('respondent_name')->nullable();
            $table->string('respondent_phone')->nullable();
            $table->string('respondent_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('form_responses', function (Blueprint $table) {
            $table->dropColumn(['respondent_name', 'respondent_phone', 'respondent_email']);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'public_token', 'public_intro', 'closes_on']);
        });
    }
};
