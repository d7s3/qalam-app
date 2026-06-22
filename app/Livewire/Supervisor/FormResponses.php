<?php

namespace App\Livewire\Supervisor;

use App\Models\Circle;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Student;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Component;

class FormResponses extends Component
{
    public int $formId;

    public Form $form;

    public string $activeTab = 'responses'; // 'responses' or 'reports'

    // Student account creation modal state
    public bool $showCreateModal = false;

    public ?int $selectedResponseId = null;

    public string $newStudentName = '';

    public string $newStudentUsername = '';

    public string $newStudentPassword = 'password';

    public ?int $newStudentCircleId = null;

    // Student linking modal state
    public bool $showLinkModal = false;

    public ?int $linkStudentId = null;

    public string $linkNameOption = 'existing'; // 'existing' or 'response'

    // Bulk creation modal state
    public bool $showBulkModal = false;

    public ?int $bulkCircleId = null;

    // Search and filters
    public string $search = '';

    public function mount(int $formId): void
    {
        $this->formId = $formId;
        $supervisorId = auth()->guard('supervisor')->id();
        $this->form = Form::where('supervisor_id', $supervisorId)->findOrFail($formId);
    }

    public function openCreateModal(int $responseId): void
    {
        $this->selectedResponseId = $responseId;
        $response = FormResponse::findOrFail($responseId);

        // Find designated name and username fields
        $nameField = collect($this->form->fields)->firstWhere('is_student_name', true);
        $usernameField = collect($this->form->fields)->firstWhere('is_student_username', true);

        $this->newStudentName = $nameField ? ($response->answers[$nameField['id']] ?? '') : '';

        $rawUsername = $usernameField ? ($response->answers[$usernameField['id']] ?? '') : '';
        // If username was provided, clean it, else generate a random email prefix
        if ($rawUsername) {
            $this->newStudentUsername = Str::slug($rawUsername);
        } else {
            $this->newStudentUsername = 'student_'.Str::random(6);
        }

        $this->newStudentPassword = 'password';
        $this->newStudentCircleId = null;
        $this->showCreateModal = true;
    }

    public function createStudentAccount(): void
    {
        $this->validate([
            'newStudentName' => 'required|string|max:255',
            'newStudentUsername' => 'required|string|max:255',
            'newStudentPassword' => 'required|string|min:6',
            'newStudentCircleId' => 'nullable|exists:circles,id',
        ]);

        // Standardize username to unique email format
        $email = $this->newStudentUsername;
        if (! str_contains($email, '@')) {
            $email = $this->newStudentUsername.'@altag-student.com';
        }

        // Handle uniqueness
        if (Student::where('email', $email)->exists()) {
            // Append random suffix
            $email = str_replace('@altag-student.com', '_'.mt_rand(100, 999).'@altag-student.com', $email);
        }

        // Create Student Account - status 'registering' (under registration) and is_approved = false
        $student = Student::create([
            'name' => $this->newStudentName,
            'email' => $email,
            'password' => bcrypt($this->newStudentPassword),
            'circle_id' => $this->newStudentCircleId ?: null,
            'status' => 'registering',
            'is_approved' => false,
        ]);

        // Link response
        $response = FormResponse::findOrFail($this->selectedResponseId);
        $response->update([
            'student_id' => $student->id,
            'is_processed' => true,
        ]);

        $this->showCreateModal = false;
        Flux::toast('تم إنشاء حساب الطالب بنجاح وتحت التسجيل', variant: 'success');
    }

    public function openLinkModal(int $responseId): void
    {
        $this->selectedResponseId = $responseId;
        $this->linkStudentId = null;
        $this->linkNameOption = 'existing';
        $this->showLinkModal = true;
    }

    public function linkToExistingStudent(): void
    {
        $this->validate([
            'linkStudentId' => 'required|exists:students,id',
            'linkNameOption' => 'required|in:existing,response',
        ]);

        $student = Student::findOrFail($this->linkStudentId);
        $response = FormResponse::findOrFail($this->selectedResponseId);

        // If supervisor chose to adopt the name in the form response
        if ($this->linkNameOption === 'response') {
            $nameField = collect($this->form->fields)->firstWhere('is_student_name', true);
            if ($nameField && isset($response->answers[$nameField['id']])) {
                $student->update(['name' => $response->answers[$nameField['id']]]);
            }
        }

        $response->update([
            'student_id' => $student->id,
            'is_processed' => true,
        ]);

        $this->showLinkModal = false;
        Flux::toast('تم ربط الرد بالطالب بنجاح', variant: 'success');
    }

    public function openBulkModal(): void
    {
        $this->bulkCircleId = null;
        $this->showBulkModal = true;
    }

    public function bulkCreateStudents(): void
    {
        $unprocessedResponses = FormResponse::where('form_id', $this->form->id)
            ->whereNull('student_id')
            ->get();

        $nameField = collect($this->form->fields)->firstWhere('is_student_name', true);
        $usernameField = collect($this->form->fields)->firstWhere('is_student_username', true);

        if (! $nameField) {
            $this->showBulkModal = false;
            Flux::toast('يرجى تحديد حقل كـ (اسم الطالب) في تصميم النموذج أولاً لتفعيل الإنشاء الجماعي.', variant: 'danger');

            return;
        }

        $createdCount = 0;

        foreach ($unprocessedResponses as $response) {
            $studentName = $response->answers[$nameField['id']] ?? null;
            if (! $studentName) {
                continue;
            }

            // Extract username or generate unique random email
            $rawUsername = $usernameField ? ($response->answers[$usernameField['id']] ?? null) : null;
            if ($rawUsername) {
                $emailPrefix = Str::slug($rawUsername);
            } else {
                $emailPrefix = 'std_'.Str::random(6);
            }

            $email = $emailPrefix.'@altag-student.com';
            if (Student::where('email', $email)->exists()) {
                $email = $emailPrefix.'_'.mt_rand(100, 999).'@altag-student.com';
            }

            // Create student in registering status and unapproved
            $student = Student::create([
                'name' => $studentName,
                'email' => $email,
                'password' => bcrypt('password'),
                'circle_id' => $this->bulkCircleId ?: null,
                'status' => 'registering',
                'is_approved' => false,
            ]);

            $response->update([
                'student_id' => $student->id,
                'is_processed' => true,
            ]);

            $createdCount++;
        }

        $this->showBulkModal = false;
        Flux::toast("تم إنشاء {$createdCount} حساب طالب تحت التسجيل بنجاح", variant: 'success');
    }

    public function deleteResponse(int $id): void
    {
        $response = FormResponse::where('form_id', $this->form->id)->findOrFail($id);
        $response->delete();

        Flux::toast('تم حذف الرد بنجاح', variant: 'success');
    }

    private function getReportsData(): array
    {
        $responses = FormResponse::where('form_id', $this->form->id)->get();
        $totalResponses = $responses->count();

        $stats = [];

        foreach ($this->form->fields as $field) {
            if (in_array($field['type'], ['select', 'multiselect'])) {
                $fieldId = $field['id'];
                $label = $field['label'];

                // Initialize option counts
                $optionCounts = [];
                foreach ($field['options'] ?? [] as $option) {
                    $optionCounts[$option] = 0;
                }

                // Process answers
                foreach ($responses as $response) {
                    $answer = $response->answers[$fieldId] ?? null;
                    if ($answer) {
                        if (is_array($answer)) {
                            foreach ($answer as $selectedOpt) {
                                if (isset($optionCounts[$selectedOpt])) {
                                    $optionCounts[$selectedOpt]++;
                                }
                            }
                        } else {
                            if (isset($optionCounts[$answer])) {
                                $optionCounts[$answer]++;
                            }
                        }
                    }
                }

                $stats[] = [
                    'label' => $label,
                    'options' => $optionCounts,
                    'total' => $totalResponses,
                ];
            }
        }

        return $stats;
    }

    public function render()
    {
        $responsesQuery = FormResponse::where('form_id', $this->form->id)
            ->with('student')
            ->latest();

        // Apply simple search filtering if any
        if ($this->search) {
            $responsesQuery->where(function ($q) {
                $q->where('answers', 'like', '%'.$this->search.'%');
            });
        }

        $responses = $responsesQuery->get();

        // Get supervisor circles for registration
        $supervisor = auth()->guard('supervisor')->user();
        $circleIds = Circle::whereIn('stage_id', $supervisor->stages()->pluck('stages.id'))->pluck('id')->toArray();
        $circles = Circle::whereIn('id', $circleIds)->get();

        // Get students listing for existing linking
        $students = Student::whereIn('circle_id', $circleIds)->orderBy('name')->get();

        $reportsData = $this->getReportsData();

        return view('livewire.supervisor.form-responses', [
            'responses' => $responses,
            'circles' => $circles,
            'students' => $students,
            'reportsData' => $reportsData,
        ]);
    }
}
