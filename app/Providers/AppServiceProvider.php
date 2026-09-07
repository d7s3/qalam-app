<?php

namespace App\Providers;

use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Blaze\Blaze;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Blaze::optimize()
            ->in(resource_path('views/components'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Relation::enforceMorphMap([
            'student' => Student::class,
            'teacher' => Teacher::class,
            'supervisor' => Supervisor::class,
            'guardian' => Guardian::class,
            'manager' => Manager::class,
        ]);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        /**
         * What a password has to be.
         *
         * It was twelve characters with an upper case, a lower case, a digit
         * and a symbol — a rule written for an administrator's console and
         * applied to children signing themselves up for a Quran circle. Nobody
         * was told it before it refused them, and a rule of that shape does not
         * produce strong passwords: it produces one strong password written on
         * a slip of paper beside the screen.
         *
         * Six characters, and the shape is the person's own business. The one
         * thing kept is the check against passwords already known to have been
         * breached — that is what actually stops «123456», and it forbids
         * nothing anybody would have thought of on their own.
         */
        Password::defaults(
            fn (): Password => app()->isProduction()
                ? Password::min(6)->uncompromised()
                : Password::min(6),
        );
    }
}
