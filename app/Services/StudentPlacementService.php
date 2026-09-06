<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * How a student comes to belong to a cohort, and how he stops.
 *
 * Two steps, and they are not the same authority. A student is first admitted
 * to a programme — by a supervisor of it or by the manager — and only then can
 * the teachers of that programme see him at all. Placement into one of its
 * cohorts is then asked for by the teacher and answered by the supervisor.
 *
 * Nothing here writes `circle_id` without leaving a record of who decided it.
 */
class StudentPlacementService
{
    /**
     * Admit a student to a programme, which is what makes him visible to it.
     *
     * Until this happens he belongs to no programme, and the pool a teacher
     * chooses from is bounded by programme — so an unadmitted student is not
     * anybody's to take.
     */
    public static function admitToProgramme(Student $student, Stage $stage, User $by): void
    {
        DB::transaction(function () use ($student, $stage, $by) {
            $student->update([
                'stage_id' => $stage->id,
                'is_approved' => true,
                'approved_by' => $by->id,
            ]);
        });
    }

    /**
     * A teacher asking for a student.
     *
     * Asking twice for the same student and cohort is the same ask, so the
     * standing request is returned rather than a second one raised: a teacher
     * clicking again because nothing visibly happened must not fill the
     * supervisor's queue with duplicates.
     */
    public static function request(Student $student, Circle $circle, User $by): StudentPlacementRequest
    {
        $standing = StudentPlacementRequest::pending()
            ->where('student_id', $student->id)
            ->where('circle_id', $circle->id)
            ->first();

        if ($standing) {
            return $standing;
        }

        $request = StudentPlacementRequest::create([
            'student_id' => $student->id,
            'circle_id' => $circle->id,
            'requested_by' => $by->id,
            'status' => StudentPlacementRequest::PENDING,
        ]);

        foreach ($circle->stage?->supervisors ?? [] as $supervisor) {
            NotificationService::notify(
                'supervisor',
                $supervisor->id,
                'placement_requested',
                __('طلب تسكين طالب'),
                __(':teacher يطلب تسكين :student في :circle', [
                    'teacher' => $by->name,
                    'student' => $student->name,
                    'circle' => $circle->name,
                ]),
                route('supervisor.placement-requests'),
            );
        }

        return $request;
    }

    /**
     * The supervisor agreeing.
     *
     * Placing the student closes every other request standing for him: once he
     * belongs somewhere, the other teachers who asked are answered rather than
     * left waiting on a queue that will never move.
     */
    public static function approve(StudentPlacementRequest $request, User $by): void
    {
        DB::transaction(function () use ($request, $by) {
            $student = $request->student;
            $circle = $request->circle;

            $student->update([
                'circle_id' => $circle->id,
                'stage_id' => $circle->stage_id,
                'joined_at' => $student->joined_at ?: now()->format('Y-m-d'),
                'is_approved' => true,
            ]);

            StudentStatusService::changeStatus($student, 'active', null, __('تسكين معتمد'));

            $request->update([
                'status' => StudentPlacementRequest::APPROVED,
                'decided_by' => $by->id,
                'decided_at' => now(),
            ]);

            StudentPlacementRequest::pending()
                ->where('student_id', $student->id)
                ->whereKeyNot($request->id)
                ->get()
                ->each(fn (StudentPlacementRequest $other) => $other->update([
                    'status' => StudentPlacementRequest::REJECTED,
                    'decided_by' => $by->id,
                    'decided_at' => now(),
                    'note' => __('سُكّن الطالب في دفعة أخرى.'),
                ]));

            self::tellRequester($request, __('تمت الموافقة على التسكين'), __(':student انضم إلى :circle', [
                'student' => $student->name,
                'circle' => $circle->name,
            ]));
        });
    }

    /** The supervisor declining, with his reason kept. */
    public static function reject(StudentPlacementRequest $request, User $by, ?string $note = null): void
    {
        $request->update([
            'status' => StudentPlacementRequest::REJECTED,
            'decided_by' => $by->id,
            'decided_at' => now(),
            'note' => $note,
        ]);

        self::tellRequester($request, __('لم تتم الموافقة على التسكين'), $note ?: __('طلب تسكين :student لم يُقبل.', [
            'student' => $request->student?->name ?? '',
        ]));
    }

    /**
     * Taking a student out of his cohort without taking away who he is.
     *
     * His programme, his record, his history and his name stay exactly as they
     * were; what changes is that he is not attending now. He used to be cleared
     * back to a pool shared by the whole academy, where any teacher anywhere
     * could pick him up again.
     */
    public static function deactivate(Student $student, ?string $notes = null): void
    {
        DB::transaction(function () use ($student, $notes) {
            $student->update(['circle_id' => null]);

            StudentStatusService::changeStatus($student, 'inactive', null, $notes ?: __('أُخرج من الدفعة'));
        });
    }

    private static function tellRequester(StudentPlacementRequest $request, string $title, string $body): void
    {
        if (! $request->requested_by) {
            return;
        }

        NotificationService::notify(
            'teacher',
            $request->requested_by,
            'placement_decided',
            $title,
            $body,
            route('teacher.students'),
        );
    }
}
