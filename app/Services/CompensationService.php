<?php

namespace App\Services;

use App\Models\Compensation;
use App\Models\OccurrenceAttendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Turning what was missed into what is owed.
 *
 * A report says a lesson was missed; this keeps it on his list until he sits
 * it. The debt is raised once and never twice, because `source_key` names the
 * miss it came from and the table refuses a second row for it.
 */
class CompensationService
{
    /**
     * Raise what this person owes for a window, and return the whole open lane.
     *
     * Only a known absence becomes a debt. An occurrence nobody answered for is
     * not yet one — it is a question, and the day screen is where it is asked.
     *
     * @return Collection<int, Compensation>
     */
    public static function raiseFor(User $user, string $role, string $from, string $to): Collection
    {
        foreach (EducationalLossService::formative($user, $role, $from, $to) as $loss) {
            if ($loss['status'] === 'unrecorded') {
                continue;
            }

            self::open(
                $user,
                Compensation::FORMATIVE,
                $loss['event']->event_name,
                'occurrence:'.$loss['event']->id.':'.$loss['date'],
                $loss['date'],
                $loss['status'] === OccurrenceAttendance::EXCUSED ? __('غياب بعذر') : __('غياب'),
            );
        }

        if ($user instanceof Student) {
            foreach (EducationalLossService::scientific($user, $from, $to) as $short) {
                if ($short['date'] === '') {
                    continue;
                }

                self::open(
                    $user,
                    Compensation::SCIENTIFIC,
                    $short['label'],
                    'content:'.$short['kind'].':'.$short['date'].':'.$short['expected'],
                    $short['date'],
                    trim(__('المطلوب').' '.$short['expected'].' · '.__('المنجز').' '.$short['done']),
                );
            }
        }

        return self::openFor($user);
    }

    /** @return Collection<int, Compensation> */
    public static function openFor(User $user): Collection
    {
        return Compensation::open()
            ->where('user_id', $user->id)
            ->orderBy('original_date')
            ->get();
    }

    public static function complete(Compensation $compensation, User $by, ?string $note = null): void
    {
        $compensation->update([
            'status' => Compensation::DONE,
            'completed_at' => now(),
            'completed_by' => $by->id,
            'note' => $note,
        ]);
    }

    /**
     * Open one debt, or leave the standing one alone.
     *
     * A debt already settled is not raised again by a later sweep of the same
     * window — closing it is a decision, and re-opening it would undo somebody's.
     */
    private static function open(
        User $user,
        string $kind,
        string $label,
        string $sourceKey,
        string $date,
        ?string $detail = null,
    ): void {
        Compensation::firstOrCreate(
            ['user_id' => $user->id, 'source_key' => $sourceKey],
            [
                'kind' => $kind,
                'label' => $label,
                'detail' => $detail,
                'original_date' => $date,
                'status' => Compensation::OPEN,
            ],
        );
    }
}
