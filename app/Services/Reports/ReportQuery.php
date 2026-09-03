<?php

namespace App\Services\Reports;

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Support\Scope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * What is being asked of a report: over whom, gathered how, and between when.
 *
 * The "over whom" is never stated by the caller — it follows from `Scope`, so a
 * report cannot be asked for students its reader may not see, however it is
 * called.
 */
class ReportQuery
{
    /** One row per student. */
    public const BY_STUDENT = 'student';

    /** One row per cohort. */
    public const BY_CIRCLE = 'circle';

    /** One row per programme. */
    public const BY_STAGE = 'stage';

    /** A single row for everything in reach. */
    public const BY_CENTRE = 'centre';

    public function __construct(
        public readonly Scope $scope,
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly string $groupBy = self::BY_STUDENT,
        /** Narrow to one student, cohort or programme within the reach. */
        public readonly ?string $subjectType = null,
        public readonly ?int $subjectId = null,
    ) {}

    /** @return array<string, string> */
    public static function groupings(): array
    {
        return [
            self::BY_STUDENT => 'كل طالب على حدة',
            self::BY_CIRCLE => 'مجموعة لكل دفعة',
            self::BY_STAGE => 'مجموعة لكل برنامج',
            self::BY_CENTRE => 'المركز كله في سطر',
        ];
    }

    /**
     * The students this report covers: those within the reader's reach, then
     * narrowed to the one student, cohort or programme he asked for.
     *
     * @return EloquentCollection<int, Student>
     */
    public function students(): EloquentCollection
    {
        $query = $this->scope->applyToStudents(
            Student::query()->with('circle.stage')->whereRoleState(fn ($q) => $q->where('is_approved', true)),
        );

        match ($this->subjectType) {
            'student' => $query->where('id', $this->subjectId),
            'circle' => $query->where('circle_id', $this->subjectId),
            'stage' => $query->whereIn('circle_id', Circle::where('stage_id', $this->subjectId)->pluck('id')),
            default => null,
        };

        return $query->orderBy('name')->get();
    }

    /**
     * The cohorts and programmes a reader may narrow to, for the pickers.
     *
     * @return array{circles: Collection<int, Circle>, stages: Collection<int, Stage>}
     */
    public function subjectChoices(): array
    {
        $circles = $this->scope->applyToCircles(Circle::query()->with('stage'))->orderBy('name')->get();

        return [
            'circles' => $circles,
            'stages' => Stage::whereIn('id', $circles->pluck('stage_id')->unique())->orderBy('name')->get(),
        ];
    }

    /**
     * The label a row is gathered under, for a student.
     */
    public function groupLabelFor(Student $student): string
    {
        return match ($this->groupBy) {
            self::BY_CIRCLE => $student->circle?->name ?? 'بلا دفعة',
            self::BY_STAGE => $student->circle?->stage?->name ?? 'بلا برنامج',
            self::BY_CENTRE => 'المركز',
            default => $student->name,
        };
    }

    public function withGrouping(string $groupBy): self
    {
        return new self($this->scope, $this->from, $this->to, $groupBy, $this->subjectType, $this->subjectId);
    }
}
