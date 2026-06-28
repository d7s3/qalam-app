<?php

namespace App\Livewire\Supervisor;

use App\Models\Circle;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Stage;
use App\Models\Student;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Component;

class FormResponses extends Component
{
    public int $formId;

    public Form $form;

    public string $activeTab = 'responses'; // 'responses' or 'reports'

    // Single student account creation modal state
    public bool $showCreateModal = false;

    public ?int $selectedResponseId = null;

    public string $newStudentName = '';

    public string $newStudentEmail = '';

    public bool $newStudentRandomEmail = false;

    public string $newStudentPhone = '';

    public string $newStudentBirthDate = '';

    public string $newStudentNationality = '';

    public string $newStudentNationalId = '';

    public string $newStudentPassword = 'password';

    public ?int $targetCircleId = null;

    public ?int $targetStageId = null;

    // Student linking modal state
    public bool $showLinkModal = false;

    public ?int $linkStudentId = null;

    public string $linkNameOption = 'existing'; // 'existing' or 'response'

    // Row selection state
    /** @var array<int, int> selected (unprocessed) response ids */
    public array $selectedResponseIds = [];

    // Bulk creation modal state
    public bool $showBulkModal = false;

    public bool $bulkSelectedOnly = false;

    /** @var array<int, int> snapshot of response ids the bulk run is scoped to */
    public array $bulkScopeIds = [];

    /** @var array<string, string|null> attribute => form field id */
    public array $bulkMap = [];

    public bool $bulkRandomEmail = false;

    public string $bulkPassword = 'password';

    public ?int $bulkCircleId = null;

    public ?int $bulkStageId = null;

    public bool $bulkAnalyzed = false;

    /** @var array<int, array<string, mixed>> */
    public array $bulkReady = [];

    /** @var array<int, array<string, mixed>> */
    public array $bulkNeedsReview = [];

    /** @var array<int, array{name: string, birth_date: string}> keyed by response id */
    public array $reviewEdits = [];

    // Search and filters
    public string $search = '';

    public function mount(int $formId): void
    {
        $this->formId = $formId;
        $supervisorId = auth()->guard('supervisor')->id();
        $this->form = Form::where('supervisor_id', $supervisorId)->findOrFail($formId);
    }

    /**
     * User attributes that can be fed from form fields.
     *
     * @return array<int, string>
     */
    private function mappableAttributes(): array
    {
        return ['name', 'email', 'phone', 'birth_date', 'nationality', 'national_id'];
    }

    /**
     * Best-effort guess of which form field feeds each user attribute, using the
     * builder designations first, then label/type heuristics.
     *
     * @return array<string, string|null>
     */
    private function guessFieldMap(): array
    {
        $fields = collect($this->form->fields);

        $byLabel = fn (array $needles): ?string => $fields->first(function ($f) use ($needles) {
            $label = $f['label'] ?? '';
            foreach ($needles as $needle) {
                if (str_contains($label, $needle)) {
                    return true;
                }
            }

            return false;
        })['id'] ?? null;

        $nameField = $fields->firstWhere('is_student_name', true);
        $emailField = $fields->firstWhere('is_student_username', true);
        $dateField = $fields->firstWhere('type', 'date');

        return [
            'name' => $nameField['id'] ?? $byLabel(['الاسم', 'اسم']),
            'email' => $emailField['id'] ?? $byLabel(['بريد', 'ايميل', 'إيميل', 'email']),
            'phone' => $byLabel(['جوال', 'هاتف', 'تواصل', 'الرقم']),
            'birth_date' => $dateField['id'] ?? $byLabel(['ميلاد', 'مواليد']),
            'nationality' => $byLabel(['جنسية']),
            'national_id' => $byLabel(['هوية', 'إقامة', 'اقامة', 'سجل']),
        ];
    }

    private function extractAnswer(FormResponse $response, ?string $fieldId): ?string
    {
        if (! $fieldId) {
            return null;
        }

        $answer = $response->answers[$fieldId] ?? null;

        return is_array($answer) ? implode(', ', $answer) : $answer;
    }

    private function normalizePhone(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        return $digits !== '' ? $digits : null;
    }

    /**
     * Parse a free-text birth date into Y-m-d, or null when it cannot be parsed.
     */
    private function parseBirthDate(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveEmail(?string $raw, bool $random): string
    {
        if ($random || ! $raw) {
            return $this->ensureUniqueEmail('std_'.Str::random(6).'@altag-student.com');
        }

        if (str_contains($raw, '@')) {
            return $this->ensureUniqueEmail($raw);
        }

        $prefix = Str::slug($raw);
        if ($prefix === '') {
            $prefix = 'std_'.Str::random(6);
        }

        return $this->ensureUniqueEmail($prefix.'@altag-student.com');
    }

    private function ensureUniqueEmail(string $email): string
    {
        if (! Student::where('email', $email)->exists()) {
            return $email;
        }

        [$local, $domain] = str_contains($email, '@') ? explode('@', $email, 2) : [$email, 'altag-student.com'];

        for ($i = 0; $i < 5; $i++) {
            $candidate = $local.'_'.mt_rand(100, 999).'@'.$domain;
            if (! Student::where('email', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'std_'.Str::random(8).'@altag-student.com';
    }

    /**
     * Resolve target circle/stage applying the precedence rule: the circle always
     * wins (its stage is the effective one), so stage_id is only stored when there
     * is no circle.
     *
     * @return array{circle_id: ?int, stage_id: ?int}
     */
    private function resolvePlacement(?int $circleId, ?int $stageId): array
    {
        if ($circleId) {
            return ['circle_id' => $circleId, 'stage_id' => null];
        }

        return ['circle_id' => null, 'stage_id' => $stageId ?: null];
    }

    /**
     * Create one student from resolved attributes and link the response to it.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function createStudent(FormResponse $response, array $attrs): Student
    {
        $placement = $this->resolvePlacement($attrs['circle_id'] ?? null, $attrs['stage_id'] ?? null);

        $student = Student::create([
            'name' => $attrs['name'],
            'email' => $attrs['email'],
            'password' => bcrypt($attrs['password']),
            'phone' => $attrs['phone'] ?? null,
            'birth_date' => $attrs['birth_date'] ?? null,
            'nationality' => $attrs['nationality'] ?? null,
            'national_id' => $attrs['national_id'] ?? null,
            'circle_id' => $placement['circle_id'],
            'stage_id' => $placement['stage_id'],
            'status' => 'registering',
            'is_approved' => false,
        ]);

        $response->update([
            'student_id' => $student->id,
            'is_processed' => true,
        ]);

        return $student;
    }

    public function openCreateModal(int $responseId): void
    {
        $this->resetValidation();
        $this->selectedResponseId = $responseId;
        $response = FormResponse::findOrFail($responseId);
        $map = $this->guessFieldMap();

        $this->newStudentName = trim((string) $this->extractAnswer($response, $map['name']));
        $rawEmail = $this->extractAnswer($response, $map['email']);
        $this->newStudentRandomEmail = empty($rawEmail);
        $this->newStudentEmail = (string) $rawEmail;
        $this->newStudentPhone = (string) $this->normalizePhone($this->extractAnswer($response, $map['phone']));
        $this->newStudentBirthDate = (string) $this->parseBirthDate($this->extractAnswer($response, $map['birth_date']));
        $this->newStudentNationality = trim((string) $this->extractAnswer($response, $map['nationality']));
        $this->newStudentNationalId = trim((string) $this->extractAnswer($response, $map['national_id']));
        $this->newStudentPassword = 'password';
        $this->targetCircleId = null;
        $this->targetStageId = null;
        $this->showCreateModal = true;
    }

    public function createStudentAccount(): void
    {
        $this->validate([
            'newStudentName' => 'required|string|max:255',
            'newStudentEmail' => 'nullable|string|max:255',
            'newStudentPhone' => 'nullable|string|max:50',
            'newStudentBirthDate' => 'nullable|date',
            'newStudentNationality' => 'nullable|string|max:255',
            'newStudentNationalId' => 'nullable|string|max:255',
            'newStudentPassword' => 'required|string|min:6',
            'targetCircleId' => 'nullable|exists:circles,id',
            'targetStageId' => 'nullable|exists:stages,id',
        ]);

        $response = FormResponse::findOrFail($this->selectedResponseId);

        $this->createStudent($response, [
            'name' => $this->newStudentName,
            'email' => $this->resolveEmail($this->newStudentEmail ?: null, $this->newStudentRandomEmail),
            'phone' => $this->normalizePhone($this->newStudentPhone),
            'birth_date' => $this->newStudentBirthDate ?: null,
            'nationality' => $this->newStudentNationality ?: null,
            'national_id' => $this->newStudentNationalId ?: null,
            'password' => $this->newStudentPassword,
            'circle_id' => $this->targetCircleId,
            'stage_id' => $this->targetStageId,
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

    /** @return array<int, int> ids of unprocessed responses for this form */
    private function unprocessedResponseIds(): array
    {
        return FormResponse::where('form_id', $this->form->id)
            ->whereNull('student_id')
            ->pluck('id')
            ->all();
    }

    public function toggleSelectAllUnprocessed(): void
    {
        $all = $this->unprocessedResponseIds();
        $this->selectedResponseIds = count($this->selectedResponseIds) >= count($all) ? [] : $all;
    }

    public function clearSelection(): void
    {
        $this->selectedResponseIds = [];
    }

    /**
     * Drop already-processed (or no longer existing) ids from the selection,
     * e.g. after some selected responses have just been turned into accounts.
     */
    private function pruneSelection(): void
    {
        $this->selectedResponseIds = array_values(array_intersect(
            array_map('intval', $this->selectedResponseIds),
            $this->unprocessedResponseIds()
        ));
    }

    public function openBulkModal(bool $selectedOnly = false): void
    {
        $this->bulkSelectedOnly = $selectedOnly;
        $this->bulkScopeIds = $selectedOnly ? array_map('intval', $this->selectedResponseIds) : [];
        $this->bulkMap = $this->guessFieldMap();
        $this->bulkRandomEmail = empty($this->bulkMap['email']);
        $this->bulkPassword = 'password';
        $this->bulkCircleId = null;
        $this->bulkStageId = null;
        $this->bulkAnalyzed = false;
        $this->bulkReady = [];
        $this->bulkNeedsReview = [];
        $this->reviewEdits = [];
        $this->showBulkModal = true;
    }

    /**
     * Sort unprocessed responses into "ready" (all mapped values valid) and
     * "needs review" (missing name or an unparseable birth date), without
     * creating anything yet.
     */
    public function analyzeBulk(): void
    {
        $query = FormResponse::where('form_id', $this->form->id)->whereNull('student_id');
        if ($this->bulkSelectedOnly) {
            $query->whereIn('id', $this->bulkScopeIds ?: [0]);
        }
        $responses = $query->get();

        $this->bulkReady = [];
        $this->bulkNeedsReview = [];
        $this->reviewEdits = [];

        foreach ($responses as $response) {
            $name = trim((string) $this->extractAnswer($response, $this->bulkMap['name'] ?? null));
            $birthRaw = $this->extractAnswer($response, $this->bulkMap['birth_date'] ?? null);
            $parsedBirth = $this->parseBirthDate($birthRaw);

            $reasons = [];
            if ($name === '') {
                $reasons[] = 'الاسم غير متوفر';
            }
            if (! empty($birthRaw) && $parsedBirth === null) {
                $reasons[] = 'تاريخ الميلاد غير صالح';
            }

            if ($reasons) {
                $this->bulkNeedsReview[] = [
                    'response_id' => $response->id,
                    'name' => $name,
                    'birth_raw' => $birthRaw,
                    'reasons' => $reasons,
                ];
                $this->reviewEdits[$response->id] = [
                    'name' => $name,
                    'birth_date' => $parsedBirth ?? '',
                ];
            } else {
                $this->bulkReady[] = [
                    'response_id' => $response->id,
                    'name' => $name,
                ];
            }
        }

        $this->bulkAnalyzed = true;
    }

    private function bulkAttrs(FormResponse $response, ?string $nameOverride = null, ?string $birthOverride = null): array
    {
        $name = $nameOverride !== null
            ? $nameOverride
            : trim((string) $this->extractAnswer($response, $this->bulkMap['name'] ?? null));

        $birth = $birthOverride !== null
            ? ($birthOverride ?: null)
            : $this->parseBirthDate($this->extractAnswer($response, $this->bulkMap['birth_date'] ?? null));

        return [
            'name' => $name,
            'email' => $this->resolveEmail($this->extractAnswer($response, $this->bulkMap['email'] ?? null), $this->bulkRandomEmail),
            'phone' => $this->normalizePhone($this->extractAnswer($response, $this->bulkMap['phone'] ?? null)),
            'birth_date' => $birth,
            'nationality' => trim((string) $this->extractAnswer($response, $this->bulkMap['nationality'] ?? null)) ?: null,
            'national_id' => trim((string) $this->extractAnswer($response, $this->bulkMap['national_id'] ?? null)) ?: null,
            'password' => $this->bulkPassword ?: 'password',
            'circle_id' => $this->bulkCircleId,
            'stage_id' => $this->bulkStageId,
        ];
    }

    /**
     * Create accounts for every "ready" response in one go. The "needs review"
     * ones are left untouched for manual handling.
     */
    public function createReadyStudents(): void
    {
        $this->validate([
            'bulkPassword' => 'required|string|min:6',
            'bulkCircleId' => 'nullable|exists:circles,id',
            'bulkStageId' => 'nullable|exists:stages,id',
        ]);

        $created = 0;
        foreach ($this->bulkReady as $row) {
            $response = FormResponse::find($row['response_id']);
            if (! $response || $response->student_id) {
                continue;
            }
            $this->createStudent($response, $this->bulkAttrs($response));
            $created++;
        }

        $this->pruneSelection();
        $this->analyzeBulk();
        Flux::toast("تم إنشاء {$created} حساب طالب بنجاح", variant: 'success');
    }

    /**
     * Create a single account from a reviewed response after the supervisor
     * corrected the flagged values.
     */
    public function createReviewedStudent(int $responseId): void
    {
        $edit = $this->reviewEdits[$responseId] ?? null;
        $name = trim((string) ($edit['name'] ?? ''));
        $birth = $edit['birth_date'] ?? '';

        if ($name === '') {
            $this->addError("reviewEdits.{$responseId}.name", 'الاسم مطلوب لإنشاء الحساب.');

            return;
        }

        if ($birth !== '' && $this->parseBirthDate($birth) === null) {
            $this->addError("reviewEdits.{$responseId}.birth_date", 'تاريخ الميلاد غير صالح.');

            return;
        }

        $response = FormResponse::find($responseId);
        if (! $response || $response->student_id) {
            return;
        }

        $this->createStudent($response, $this->bulkAttrs($response, $name, $birth));

        $this->pruneSelection();
        $this->analyzeBulk();
        Flux::toast('تم إنشاء حساب الطالب بنجاح', variant: 'success');
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

                $optionCounts = [];
                foreach ($field['options'] ?? [] as $option) {
                    $optionCounts[$option] = 0;
                }

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
        $responses = FormResponse::where('form_id', $this->form->id)
            ->with('student')
            ->latest()
            ->get();

        // Search every answer value across all fields. Filtering in PHP (on the
        // decoded answers) is reliable for Arabic, unlike a LIKE on the JSON column
        // whose unicode is escaped (\uXXXX) at rest.
        $term = trim($this->search);
        if ($term !== '') {
            $responses = $responses->filter(function (FormResponse $response) use ($term) {
                foreach ((array) $response->answers as $value) {
                    $value = is_array($value) ? implode(' ', $value) : (string) $value;
                    if (mb_stripos($value, $term) !== false) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        $supervisor = auth()->guard('supervisor')->user();
        $scopedCircleIds = Circle::whereIn('stage_id', $supervisor->stages()->pluck('stages.id'))->pluck('id');

        // The supervisor may place new students into any stage or circle, not only
        // their own scope.
        $circles = Circle::with('stage')->orderBy('name')->get();
        $stages = Stage::orderBy('name')->get();

        // Linking a response to an existing student stays within the supervisor's
        // own students.
        $students = Student::whereIn('circle_id', $scopedCircleIds)->orderBy('name')->get();

        $reportsData = $this->getReportsData();

        return view('livewire.supervisor.form-responses', [
            'responses' => $responses,
            'circles' => $circles,
            'stages' => $stages,
            'students' => $students,
            'reportsData' => $reportsData,
            'unprocessedCount' => $responses->whereNull('student_id')->count(),
        ]);
    }
}
