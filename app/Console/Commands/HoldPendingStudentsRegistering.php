<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\StudentStatusService;
use App\Support\StudentStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Put back تحت التسجيل the students who signed themselves up and are still
 * waiting to be approved.
 *
 * Until October 2026 a student who signed himself up started مشارك, from the
 * column's default, and so sat in competitions, public results and every count
 * of active students before anyone had looked at him. New ones start تحت
 * التسجيل now; this brings the ones already waiting into line, each with a row
 * in his history saying why. A student already approved, or turned down, is
 * left as he is. Run once after deploying; running it again changes nothing.
 */
class HoldPendingStudentsRegistering extends Command
{
    protected $signature = 'students:hold-pending {--dry-run : عرض العدد فقط دون تغيير}';

    protected $description = 'يجعل كل طالب ينتظر حسابه الموافقة «تحت التسجيل» بدل «مشارك»';

    public function handle(): int
    {
        $waiting = Student::query()
            ->whereRoleState(fn (Builder $role) => $role
                ->where('is_approved', false)
                ->where(fn (Builder $notTurnedDown) => $notTurnedDown->whereNull('is_rejected')->orWhere('is_rejected', false)))
            ->where(fn (Builder $status) => $status
                ->where('status', StudentStatus::Active->value)
                ->orWhereNull('status')
                ->orWhere('status', ''))
            ->get();

        if ($waiting->isEmpty()) {
            $this->info('لا يوجد طالب ينتظر الموافقة وحالته «مشارك».');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn("{$waiting->count()} طالب ينتظر الموافقة وحالته «مشارك». لم يُغيَّر شيء.");

            return self::SUCCESS;
        }

        foreach ($waiting as $student) {
            StudentStatusService::changeStatus(
                $student,
                StudentStatus::Registering->value,
                null,
                'حسابه بانتظار الموافقة، فهو تحت التسجيل لا مشارك',
            );
        }

        $this->info("صار {$waiting->count()} طالب «تحت التسجيل».");

        return self::SUCCESS;
    }
}
