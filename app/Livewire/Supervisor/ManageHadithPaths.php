<?php

namespace App\Livewire\Supervisor;

use App\Models\Circle;
use App\Models\HadithPath;
use App\Models\HadithPathDay;
use App\Models\HadithText;
use App\Models\StudentHadithPlan;
use App\Models\StudentHadithPlanDay;
use Flux\Flux;
use Livewire\Component;

class ManageHadithPaths extends Component
{
    public string $search = '';

    // Path form fields
    public ?int $editingPathId = null;

    public ?int $hadithTextId = null;

    public string $name = '';

    public string $memorizeType = 'hadiths'; // 'hadiths' or 'lines'

    public int $memorizeAmount = 1;

    public string $startDate = '';

    public function mount(): void
    {
        $this->startDate = now()->format('Y-m-d');
    }

    public function createPath(): void
    {
        $this->reset(['editingPathId', 'hadithTextId', 'name', 'memorizeType', 'memorizeAmount']);
        $this->startDate = now()->format('Y-m-d');
        Flux::modal('path-modal')->show();
    }

    public function editPath(int $id): void
    {
        $path = HadithPath::findOrFail($id);
        $this->editingPathId = $path->id;
        $this->hadithTextId = $path->hadith_text_id;
        $this->name = $path->name;
        $this->memorizeType = $path->memorize_type;
        $this->memorizeAmount = $path->memorize_amount;
        $this->startDate = $path->start_date->format('Y-m-d');
        Flux::modal('path-modal')->show();
    }

    public function savePath(): void
    {
        $this->validate([
            'hadithTextId' => 'required|exists:hadith_texts,id',
            'name' => 'required|string|max:255',
            'memorizeType' => 'required|in:hadiths,lines',
            'memorizeAmount' => 'required|integer|min:1',
            'startDate' => 'required|date',
        ]);

        if ($this->editingPathId) {
            $path = HadithPath::findOrFail($this->editingPathId);
            $path->update([
                'hadith_text_id' => $this->hadithTextId,
                'name' => $this->name,
                'memorize_type' => $this->memorizeType,
                'memorize_amount' => $this->memorizeAmount,
                'start_date' => $this->startDate,
            ]);
            Flux::toast('تم تعديل مسار الحفظ بنجاح', variant: 'success');
        } else {
            HadithPath::create([
                'hadith_text_id' => $this->hadithTextId,
                'name' => $this->name,
                'memorize_type' => $this->memorizeType,
                'memorize_amount' => $this->memorizeAmount,
                'start_date' => $this->startDate,
            ]);
            Flux::toast('تم إنشاء مسار الحفظ بنجاح', variant: 'success');
        }

        Flux::modal('path-modal')->close();
        $this->reset(['editingPathId', 'hadithTextId', 'name', 'memorizeType', 'memorizeAmount']);
    }

    public function deletePath(int $id): void
    {
        $path = HadithPath::findOrFail($id);
        $path->delete();
        Flux::toast('تم حذف مسار الحفظ بنجاح', variant: 'success');
    }

    // Enrollment fields
    public ?int $enrollingPathId = null;

    public array $selectedStudentIds = [];

    public string $studentSearch = '';

    public function showEnrollModal(int $pathId): void
    {
        $this->enrollingPathId = $pathId;
        $this->selectedStudentIds = StudentHadithPlan::where('hadith_path_id', $pathId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
        $this->studentSearch = '';
        Flux::modal('enroll-modal')->show();
    }

    public function toggleSelectAll(array $allIds): void
    {
        $allIds = array_map('intval', $allIds);
        $selectedIds = array_map('intval', $this->selectedStudentIds);
        $alreadySelectedCount = count(array_intersect($selectedIds, $allIds));

        if ($alreadySelectedCount === count($allIds)) {
            // Deselect all
            $newSelected = array_diff($selectedIds, $allIds);
        } else {
            // Select all
            $newSelected = array_unique(array_merge($selectedIds, $allIds));
        }
        $this->selectedStudentIds = array_values(array_map('strval', $newSelected));
    }

    public function toggleSelectCircle(array $studentIds): void
    {
        $studentIds = array_map('intval', $studentIds);
        $selectedIds = array_map('intval', $this->selectedStudentIds);
        $alreadySelectedCount = count(array_intersect($selectedIds, $studentIds));

        if ($alreadySelectedCount === count($studentIds)) {
            // Deselect these students
            $newSelected = array_diff($selectedIds, $studentIds);
        } else {
            // Select these students
            $newSelected = array_unique(array_merge($selectedIds, $studentIds));
        }
        $this->selectedStudentIds = array_values(array_map('strval', $newSelected));
    }

    public function enrollStudents(): void
    {
        if (! $this->enrollingPathId) {
            return;
        }

        $path = HadithPath::findOrFail($this->enrollingPathId);
        $pathDays = HadithPathDay::where('hadith_path_id', $path->id)->orderBy('day_number')->get();

        if ($pathDays->isEmpty()) {
            Flux::toast('الرجاء إعداد جدول الحفظ للمسار أولاً قبل تسكين الطلاب.', variant: 'danger');

            return;
        }

        $selectedIds = array_map('intval', $this->selectedStudentIds);

        // Get currently active student IDs for this path
        $currentlyEnrolledStudentIds = StudentHadithPlan::where('hadith_path_id', $path->id)
            ->where('status', 'active')
            ->pluck('student_id')
            ->toArray();

        // 1. De-enroll students who were unchecked
        $toDeEnroll = array_diff($currentlyEnrolledStudentIds, $selectedIds);
        if (! empty($toDeEnroll)) {
            StudentHadithPlan::where('hadith_path_id', $path->id)
                ->whereIn('student_id', $toDeEnroll)
                ->where('status', 'active')
                ->update(['status' => 'suspended']);
        }

        // If no students are checked at all
        if (empty($selectedIds)) {
            Flux::toast('تم إلغاء تسكين جميع الطلاب من المسار', variant: 'success');
            Flux::modal('enroll-modal')->close();
            $this->reset(['enrollingPathId', 'selectedStudentIds', 'studentSearch']);

            return;
        }

        // 2. Enroll new students who were checked
        $toEnroll = array_diff($selectedIds, $currentlyEnrolledStudentIds);
        foreach ($toEnroll as $studentId) {
            // Deactivate any existing active hadith plan for the student (on other paths)
            StudentHadithPlan::where('student_id', $studentId)
                ->where('status', 'active')
                ->update(['status' => 'suspended']);

            // Create new plan
            $studentPlan = StudentHadithPlan::create([
                'student_id' => $studentId,
                'hadith_path_id' => $path->id,
                'start_date' => $path->start_date,
                'status' => 'active',
                'created_by_role' => 'supervisor',
            ]);

            // Copy days
            foreach ($pathDays as $pDay) {
                StudentHadithPlanDay::create([
                    'student_hadith_plan_id' => $studentPlan->id,
                    'hadith_path_day_id' => $pDay->id,
                    'date' => $pDay->date,
                    'day_name' => $pDay->day_name,
                    'memorize_type' => $pDay->memorize_type,
                    'memorize_amount' => $pDay->memorize_amount,
                    'from_hadith_id' => $pDay->from_hadith_id,
                    'to_hadith_id' => $pDay->to_hadith_id,
                    'from_line_number' => $pDay->from_line_number,
                    'to_line_number' => $pDay->to_line_number,
                    'review_from_hadith_id' => $pDay->review_from_hadith_id,
                    'review_to_hadith_id' => $pDay->review_to_hadith_id,
                    'review_from_line_number' => $pDay->review_from_line_number,
                    'review_to_line_number' => $pDay->review_to_line_number,
                ]);
            }
        }

        Flux::toast('تم تسكين الطلاب بنجاح في المسار', variant: 'success');
        Flux::modal('enroll-modal')->close();
        $this->reset(['enrollingPathId', 'selectedStudentIds', 'studentSearch']);
    }

    private function getSupervisorCircleIds(): array
    {
        $supervisor = auth()->guard('supervisor')->user();
        if (! $supervisor) {
            return [];
        }

        return Circle::whereIn('stage_id', $supervisor->stages()->pluck('stages.id'))->pluck('id')->toArray();
    }

    public function render()
    {
        $query = HadithPath::query()->with('text');
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhereHas('text', function ($sq) {
                        $sq->where('name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        $paths = $query->latest()->get();
        $texts = HadithText::orderBy('name')->get();

        return view('livewire.supervisor.manage-hadith-paths', [
            'paths' => $paths,
            'texts' => $texts,
        ])->layout('layouts.role-shell');
    }
}
