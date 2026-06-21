<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\CircleResource;
use App\Http\Resources\StudentAttendanceResource;
use App\Models\Attendance;
use App\Models\Student;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $teacher = $request->user();
        $circles = $teacher->circles()->get();

        if ($request->filled('circle_id')) {
            $circle = $teacher->circles()->where('circles.id', $request->circle_id)->first();

            if (! $circle) {
                return response()->json([
                    'message' => 'غير مصرح لك بالوصول لهذه الحلقة.',
                ], 403);
            }

            $date = $request->input('date', now()->format('Y-m-d'));

            $studentsQuery = Student::where('circle_id', $circle->id)
                ->where('is_approved', true)
                ->where(function ($query) use ($date) {
                    $query->whereNull('joined_at')
                        ->orWhere('joined_at', '<=', $date);
                })
                ->with([
                    'circle',
                    'statusHistories' => function ($query) use ($date) {
                        $query->where('start_date', '<=', $date)->orderBy('start_date', 'desc');
                    },
                ])
                ->orderBy('name')
                ->get();

            $students = $studentsQuery->filter(function ($student) {
                $history = $student->statusHistories->first();
                $statusOnDate = $history ? $history->status : $student->status;

                return in_array($statusOnDate, ['active', 'registering']);
            })->values();

            $existing = Attendance::where('circle_id', $circle->id)
                ->whereDate('date', $date)
                ->pluck('status', 'student_id')
                ->toArray();

            foreach ($students as $student) {
                $student->attendance_status = $existing[$student->id] ?? '';
            }

            return response()->json([
                'circles' => CircleResource::collection($circles),
                'selected_circle' => new CircleResource($circle),
                'students' => StudentAttendanceResource::collection($students),
            ]);
        }

        return response()->json([
            'circles' => CircleResource::collection($circles),
            'selected_circle' => null,
            'students' => [],
        ]);
    }

    public function store(Request $request)
    {
        $teacher = $request->user();

        $request->validate([
            'circle_id' => 'required|exists:circles,id',
            'date' => 'required|date',
            'records' => 'required|array',
        ]);

        $circle = $teacher->circles()->where('circles.id', $request->circle_id)->first();

        if (! $circle) {
            return response()->json([
                'message' => 'غير مصرح لك بالوصول لهذه الحلقة.',
            ], 403);
        }

        DB::transaction(function () use ($request, $teacher, $circle) {
            foreach ($request->records as $studentId => $status) {
                if (! in_array($status, ['present', 'absent', 'late', 'excused'])) {
                    continue;
                }

                $student = Student::where('id', $studentId)->where('circle_id', $circle->id)->first();
                if (! $student) {
                    continue;
                }

                $existing = Attendance::where('student_id', $studentId)
                    ->whereDate('date', $request->date)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'teacher_id' => $teacher->id,
                        'circle_id' => $circle->id,
                        'status' => $status,
                    ]);
                } else {
                    $existing = Attendance::create([
                        'student_id' => $studentId,
                        'date' => $request->date,
                        'teacher_id' => $teacher->id,
                        'circle_id' => $circle->id,
                        'status' => $status,
                    ]);
                }

                GamificationService::syncStudentAttendanceXP($existing);
            }
        });

        return response()->json([
            'message' => 'تم تسجيل حضور الطلاب بنجاح.',
        ]);
    }
}
