<?php

namespace App\Livewire\Guardian;

use App\Models\Challenge;
use App\Models\ChallengeItem;
use App\Models\Student;
use App\Models\StudentPlan;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateChallenge extends Component
{
    public $studentId;

    public $student;

    public $studentPlans;

    public function mount($studentId)
    {
        $this->studentId = $studentId;
        $this->student = Student::where('id', $studentId)
            ->where('guardian_id', Auth::guard('guardian')->id())
            ->firstOrFail();

        $this->studentPlans = StudentPlan::where('student_id', $this->studentId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function saveChallenge($data)
    {
        // $data comes from Alpine component
        $challenge = Challenge::create([
            'guardian_id' => Auth::guard('guardian')->id(),
            'student_id' => $this->studentId,
            'start_date' => $data['startDate'] ?? now()->toDateString(),
            'end_date' => $data['endDate'] ?? now()->addDays(7)->toDateString(),
            'prize_type' => $data['prizeType'],
            'prize_description' => $data['prizeDescription'],
            'status' => 'pending',
        ]);

        if ($data['attendanceEnabled']) {
            ChallengeItem::create([
                'challenge_id' => $challenge->id,
                'type' => 'attendance',
                'target_value' => $data['attendanceDays'],
                'metadata' => [
                    'mode' => $data['attendanceMode'], // 'consecutive' or 'range'
                    'max_absences' => $data['attendanceMaxAbsences'] ?? 0,
                    'no_lateness' => $data['attendanceNoLateness'] ?? false,
                ],
            ]);
        }

        if ($data['recitationEnabled']) {
            if ($data['recitationAmountEnabled']) {
                ChallengeItem::create([
                    'challenge_id' => $challenge->id,
                    'type' => 'recitation_amount',
                    'target_value' => $data['recitationAmountTarget'],
                    'metadata' => [
                        'plan_ids' => $data['recitationPlans'],
                        'target_type' => $data['recitationAmountType'], // 'pages', 'surahs', 'percentage'
                        'start_date' => $data['recitationStartDate'],
                        'end_date' => $data['recitationEndDate'],
                    ],
                ]);
            }

            if ($data['recitationQualityEnabled']) {
                ChallengeItem::create([
                    'challenge_id' => $challenge->id,
                    'type' => 'recitation_quality',
                    'target_value' => 0, // No specific number, just maintain quality
                    'metadata' => [
                        'plan_ids' => $data['recitationPlans'],
                        'quality_requirement' => $data['recitationQualityRequirement'], // 'excellent', 'good_or_better'
                        'start_date' => $data['recitationStartDate'],
                        'end_date' => $data['recitationEndDate'],
                    ],
                ]);
            }
        }

        session()->flash('status', 'تم إنشاء التحدي بنجاح، وهو الآن بانتظار قبول ابنك!');

        return redirect()->route('guardian.dashboard');
    }

    public function canProceed()
    {
        return $this->step === 1 ||
            ($this->step === 2 && $this->canProceedToStep3()) ||
            ($this->step === 3 && $this->canProceedToStep4());
    }

    public function render()
    {
        return view('livewire.guardian.create-challenge');
    }
}
