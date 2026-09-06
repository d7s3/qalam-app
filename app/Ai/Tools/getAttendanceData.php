<?php

namespace App\Ai\Tools;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class getAttendanceData implements Tool
{
    /**
     * A whole academy-wide day is a few hundred rows; this keeps a careless
     * range from flooding the model's context.
     */
    private const MAX_ROWS = 3000;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get attendance records (التحضير) grouped by date, stage, circle, teacher and student, with each student marked '
            .'حاضر/غائب/متأخر/مستأذن. Always pass "from" and "to" (Y-m-d) to bound the range, and narrow further with '
            .'"circle", "stage" or "student". Set "summary" to true to get counts per circle instead of the name-by-name '
            .'list, which is what you want for any period longer than a few days.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = Attendance::with([
            'student:name,id',
            'teacher:name,id',
            'circle:name,id,stage_id',
            'circle.stage:name,id',
        ]);

        if ($from = ($request['from'] ?? null)) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = ($request['to'] ?? null)) {
            $query->whereDate('date', '<=', $to);
        }

        if ($circle = ($request['circle'] ?? null)) {
            $query->whereHas('circle', fn ($q) => $q->where('name', 'like', '%'.$circle.'%'));
        }

        if ($stage = ($request['stage'] ?? null)) {
            $query->whereHas('circle.stage', fn ($q) => $q->where('name', 'like', '%'.$stage.'%'));
        }

        if ($student = ($request['student'] ?? null)) {
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', '%'.$student.'%'));
        }

        $total = $query->count();

        $records = $query->orderBy('date')
            ->limit(self::MAX_ROWS)
            ->get(['date', 'status', 'student_id', 'teacher_id', 'circle_id']);

        $payload = [
            'matching_records' => $total,
            'returned_records' => $records->count(),
            'note' => $total > $records->count()
                ? 'Only the first '.self::MAX_ROWS.' records are included. Narrow the range or use "summary" for the full picture.'
                : null,
        ];

        $payload[($request['summary'] ?? null) ? 'summary' : 'attendance'] = ($request['summary'] ?? null)
            ? $this->summarize($records)
            : $this->detail($records);

        return json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Name-by-name attendance, nested date > stage > circle > teacher > student.
     *
     * @param  Collection<int, Attendance>  $records
     */
    private function detail($records): mixed
    {
        return $records
            ->groupBy(fn ($a) => Carbon::parse($a->date)->format('Y-m-d'))
            ->map(function ($dateGroup) {
                return $dateGroup->groupBy(fn ($a) => $a->circle?->stage?->name ?? 'غير محدد')
                    ->map(function ($stageGroup) {
                        return $stageGroup->groupBy(fn ($a) => $a->circle?->name ?? 'غير محدد')
                            ->map(function ($circleGroup) {
                                return $circleGroup->groupBy(fn ($a) => $a->teacher?->name ?? 'غير محدد')
                                    ->map(function ($teacherGroup) {
                                        return $teacherGroup->mapWithKeys(fn ($a) => [
                                            $a->student?->name ?? 'غير محدد' => $this->statusLabel($a->status),
                                        ]);
                                    });
                            });
                    });
            });
    }

    /**
     * Status counts per stage and circle over the whole range.
     *
     * @param  Collection<int, Attendance>  $records
     */
    private function summarize($records): mixed
    {
        return $records
            ->groupBy(fn ($a) => $a->circle?->stage?->name ?? 'غير محدد')
            ->map(function ($stageGroup) {
                return $stageGroup->groupBy(fn ($a) => $a->circle?->name ?? 'غير محدد')
                    ->map(function ($circleGroup) {
                        $counts = ['حاضر' => 0, 'غائب' => 0, 'متأخر' => 0, 'مستأذن' => 0];

                        foreach ($circleGroup as $record) {
                            $label = $this->statusLabel($record->status);
                            if (isset($counts[$label])) {
                                $counts[$label]++;
                            }
                        }

                        return $counts;
                    });
            });
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'present' => 'حاضر',
            'absent' => 'غائب',
            'excused' => 'مستأذن',
            'late' => 'متأخر',
            default => (string) $status,
        };
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Start of the range (Y-m-d).'),
            'to' => $schema->string()->description('End of the range (Y-m-d).'),
            'circle' => $schema->string()->description('Circle name (دفعة), or part of it.'),
            'stage' => $schema->string()->description('Stage name (برنامج), or part of it.'),
            'student' => $schema->string()->description('Student name, or part of it.'),
            'summary' => $schema->boolean()->description('Return status counts per circle instead of the name-by-name list.'),
        ];
    }
}
