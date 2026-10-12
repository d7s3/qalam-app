<?php

namespace App\Services;

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Models\Supervisor;
use App\Models\User;
use App\Support\Scope;
use App\Support\StudentStatus;
use Illuminate\Support\Collection;
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

        foreach (self::supervisorsOf($circle) as $supervisor) {
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
     * A student asking for a group himself.
     *
     * He names his sheikh's group and waits for the sheikh to accept him. One
     * ask stands at a time: choosing again withdraws the one before, so a
     * sheikh is never left holding a request the student has moved on from.
     */
    public static function askToJoin(Student $student, Circle $circle): StudentPlacementRequest
    {
        abort_if($student->circle_id !== null, 403, __('أنت في دفعة بالفعل.'));

        return DB::transaction(function () use ($student, $circle) {
            StudentPlacementRequest::pending()
                ->where('student_id', $student->id)
                ->where('requested_by', $student->id)
                ->where('circle_id', '!=', $circle->id)
                ->update([
                    'status' => StudentPlacementRequest::REJECTED,
                    'decided_at' => now(),
                    'note' => __('اختار الطالب مجموعة أخرى.'),
                ]);

            return self::request($student, $circle, $student);
        });
    }

    /**
     * The groups a student may ask to join: every cohort, each named by the
     * sheikhs narrowed to it, with how many students it holds.
     *
     * @return Collection<int, array{circle: Circle, sheikhs: list<string>, students: int}>
     */
    public static function groupsToChoose(): Collection
    {
        $sheikhs = [];

        foreach (Supervisor::with('roles')->get() as $supervisor) {
            $reach = Scope::for($supervisor, 'supervisor');

            if ($reach->narrowsToCohorts()) {
                foreach ($reach->reachableCircleIds() as $circleId) {
                    $sheikhs[$circleId][] = $supervisor->name;
                }
            }
        }

        return Circle::with('stage')
            ->withCount(['students' => fn ($query) => $query->where('status', StudentStatus::Active->value)])
            ->orderBy('name')
            ->get()
            ->map(fn (Circle $circle) => [
                'circle' => $circle,
                'sheikhs' => $sheikhs[$circle->id] ?? [],
                'students' => $circle->students_count,
            ])
            ->sortBy(fn (array $group) => $group['sheikhs'] === [] ? 1 : 0)
            ->values();
    }

    /**
     * Who answers for a cohort: the supervisors whose reach takes it in.
     *
     * Every supervisor of its programme used to be told, so a student asking
     * for one sheikh's group went to all five of them.
     *
     * @return Collection<int, Supervisor>
     */
    public static function supervisorsOf(Circle $circle): Collection
    {
        return ($circle->stage?->supervisors()->with('roles')->get() ?? collect())
            ->filter(fn (User $supervisor) => Scope::for($supervisor, 'supervisor')->reachableCircleIds()->contains($circle->id))
            ->values();
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

            self::settle($student, $circle, __('تسكين معتمد'));

            $request->update([
                'status' => StudentPlacementRequest::APPROVED,
                'decided_by' => $by->id,
                'decided_at' => now(),
            ]);

            self::closeOtherAsks($student, $by, $request);

            self::tellRequester($request, __('تمت الموافقة على التسكين'), __(':student انضم إلى :circle', [
                'student' => $student->name,
                'circle' => $circle->name,
            ]));
        });
    }

    /**
     * A sheikh taking into his group a student who has asked for none.
     *
     * The same placement an accepted ask makes, written to the student's
     * history under the sheikh's name.
     */
    public static function placeDirectly(Student $student, Circle $circle, User $by): void
    {
        DB::transaction(function () use ($student, $circle, $by) {
            self::settle($student, $circle, __('ضمّه :name إلى مجموعته', ['name' => $by->name]));
            self::closeOtherAsks($student, $by);
        });

        NotificationService::notify(
            'student',
            $student->id,
            'placement_decided',
            __('انضممت إلى مجموعة'),
            __('أنت الآن في :circle', ['circle' => trim($circle->name)]),
            route('student.dashboard'),
        );
    }

    /** Putting a student in a cohort: his cohort, his programme, and مشارك. */
    private static function settle(Student $student, Circle $circle, string $note): void
    {
        $student->update([
            'circle_id' => $circle->id,
            'stage_id' => $circle->stage_id,
            'joined_at' => $student->joined_at ?: now()->format('Y-m-d'),
            'is_approved' => true,
        ]);

        StudentStatusService::changeStatus($student, StudentStatus::Active->value, null, $note);
    }

    /**
     * Once he belongs somewhere, every other ask standing for him is answered
     * rather than left waiting on a queue that will never move.
     */
    private static function closeOtherAsks(Student $student, User $by, ?StudentPlacementRequest $except = null): void
    {
        StudentPlacementRequest::pending()
            ->where('student_id', $student->id)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->get()
            ->each(fn (StudentPlacementRequest $other) => $other->update([
                'status' => StudentPlacementRequest::REJECTED,
                'decided_by' => $by->id,
                'decided_at' => now(),
                'note' => __('سُكّن الطالب في دفعة أخرى.'),
            ]));
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

            StudentStatusService::changeStatus($student, StudentStatus::Inactive->value, null, $notes ?: __('أُخرج من الدفعة'));
        });
    }

    private static function tellRequester(StudentPlacementRequest $request, string $title, string $body): void
    {
        if (! $request->requested_by) {
            return;
        }

        // A student who asked for a group himself hears the answer himself.
        $askedForHimself = (int) $request->requested_by === (int) $request->student_id;

        NotificationService::notify(
            $askedForHimself ? 'student' : 'teacher',
            $request->requested_by,
            'placement_decided',
            $title,
            $body,
            route($askedForHimself ? 'student.dashboard' : 'teacher.students'),
        );
    }
}
