<?php

namespace App\Livewire\Teacher;

use App\Models\GamificationTransaction;
use App\Models\Leaderboard;
use App\Models\LeaderboardScore;
use App\Models\Student;
use App\Services\GamificationService;
use App\Services\LeaderboardService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class LeaderboardGrade extends Component
{
    #[On('student-list-updated')]
    public function refreshStudents(): void
    {
        // Livewire automatically re-renders
    }

    public $leaderboardId;

    public $date;

    // Modal state kept for backward-compat but not used in inline flow
    public $showExtraPointsModal = false;

    public function mount($leaderboardId)
    {
        $this->leaderboardId = $leaderboardId;
        $this->date = now()->format('Y-m-d');
    }

    public function toggleScore($studentId, $criterionId, $points)
    {
        $score = LeaderboardScore::where('leaderboard_id', $this->leaderboardId)
            ->where('student_id', $studentId)
            ->where('leaderboard_criterion_id', $criterionId)
            ->whereDate('date', Carbon::parse($this->date))
            ->first();

        if ($score) {
            $scoreId = $score->id;
            $score->delete(); // Untoggle

            // Delete corresponding gamification transaction if any
            GamificationTransaction::where('reference_type', LeaderboardScore::class)
                ->where('reference_id', $scoreId)
                ->delete();
            GamificationService::recalculateStudentState($studentId, $this->leaderboardId);
            GamificationService::syncStudentBadges($studentId, $this->leaderboardId);
        } else {
            $newScore = LeaderboardScore::create([
                'leaderboard_id' => $this->leaderboardId,
                'student_id' => $studentId,
                'leaderboard_criterion_id' => $criterionId,
                'date' => $this->date,
            ]);

            GamificationService::syncStudentCustomCriterionXP($newScore);
        }
    }

    public function saveExtraPoints(int $studentId, int|float $amount, string $notes): void
    {
        $this->validate([
            'date' => 'required|date',
        ]);

        if (! $amount || ! $notes) {
            return;
        }

        $id = DB::table('leaderboard_extra_points')->insertGetId([
            'leaderboard_id' => $this->leaderboardId,
            'student_id' => $studentId,
            'date' => $this->date,
            'points' => $amount,
            'notes' => $notes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        GamificationService::syncStudentExtraPointsXP($id);

        Flux::toast('تم حفظ النقاط الإضافية بنجاح', variant: 'success');
    }

    public function deleteExtraPoints($id)
    {
        DB::table('leaderboard_extra_points')->where('id', $id)->delete();
        GamificationService::syncStudentExtraPointsXP($id);
    }

    public function approveBadge(int $badgeId, int $studentId): void
    {
        DB::table('gamification_badge_student')
            ->where('badge_id', $badgeId)
            ->where('student_id', $studentId)
            ->update([
                'status' => 'approved',
                'updated_at' => now(),
            ]);

        Flux::toast('تم اعتماد منح الوسام للطالب بنجاح', variant: 'success');
    }

    public function rejectBadge(int $badgeId, int $studentId): void
    {
        DB::table('gamification_badge_student')
            ->where('badge_id', $badgeId)
            ->where('student_id', $studentId)
            ->delete();

        Flux::toast('تم رفض منح الوسام', variant: 'neutral');
    }

    public function render()
    {
        $leaderboard = Leaderboard::with('criteria', 'circles')->findOrFail($this->leaderboardId);

        $teacher = auth()->guard('teacher')->user();
        $circleId = $teacher ? ($teacher->circles()->first()->id ?? $leaderboard->circle_id) : $leaderboard->circle_id;

        $students = Student::where('circle_id', $circleId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $scores = LeaderboardScore::where('leaderboard_id', $this->leaderboardId)
            ->whereDate('date', \Carbon\Carbon::parse($this->date))
            ->get()
            ->groupBy('student_id')
            ->map(function ($studentScores) {
                return $studentScores->pluck('leaderboard_criterion_id')->toArray();
            });

        $extraPointsMap = DB::table('leaderboard_extra_points')
            ->where('leaderboard_id', $this->leaderboardId)
            ->whereDate('date', \Carbon\Carbon::parse($this->date))
            ->get()
            ->groupBy('student_id');

        $service = new LeaderboardService;
        $dailyScores = $service->getDailyScores($leaderboard, \Carbon\Carbon::parse($this->date)->format('Y-m-d'));

        $pendingBadges = DB::table('gamification_badge_student')
            ->join('gamification_badges', 'gamification_badges.id', '=', 'gamification_badge_student.badge_id')
            ->join('students', 'students.id', '=', 'gamification_badge_student.student_id')
            ->where('gamification_badges.leaderboard_id', $this->leaderboardId)
            ->where('gamification_badge_student.status', 'pending_approval')
            ->select('gamification_badge_student.*', 'gamification_badges.name as badge_name', 'gamification_badges.icon as badge_icon', 'students.name as student_name')
            ->get();

        return view('livewire.teacher.leaderboard-grade', [
            'leaderboard' => $leaderboard,
            'students' => $students,
            'scoresMap' => $scores,
            'extraPointsMap' => $extraPointsMap,
            'dailyScores' => $dailyScores,
            'pendingBadges' => $pendingBadges,
        ]);
    }
}
