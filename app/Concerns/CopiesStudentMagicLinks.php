<?php

namespace App\Concerns;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Adds student multi-selection to a student-list component, together with a
 * copyable text block pairing every selected student with their magic login
 * link.
 *
 * Consuming components supply two queries: the role-scoped set of students the
 * component may ever act on, and that same set narrowed by the on-screen
 * filters.
 */
trait CopiesStudentMagicLinks
{
    /** @var array<int, string> */
    public array $selectedStudentIds = [];

    public bool $selectAll = false;

    /**
     * Every student this component is allowed to act on, before any filtering.
     *
     * @return Builder<Student>
     */
    abstract protected function selectableStudentsQuery();

    /**
     * The students currently listed on screen, with the active filters applied.
     *
     * @return Builder<Student>
     */
    abstract protected function filteredStudentsQuery();

    /**
     * The selected students, re-resolved through the component's own scope so a
     * tampered id can never widen what the component touches.
     *
     * @return Builder<Student>
     */
    protected function selectedStudentsQuery()
    {
        return $this->selectableStudentsQuery()->whereIn('id', $this->selectedStudentIds);
    }

    public function resetStudentSelection(): void
    {
        $this->selectedStudentIds = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selectedStudentIds = $value
            ? $this->filteredStudentsQuery()->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    /**
     * Build a shareable text block pairing every selected student with their
     * magic login link, issuing a token to accounts that never had one.
     */
    public function buildSelectedMagicLinksText(): string
    {
        if ($this->selectedStudentIds === []) {
            return '';
        }

        $students = $this->selectedStudentsQuery()->orderBy('name')->get();

        if ($students->isEmpty()) {
            return '';
        }

        $lines = $students->map(function (Student $student) {
            if (blank($student->access_token)) {
                $student->update(['access_token' => Str::random(32)]);
            }

            return "⦿ {$student->name}:\n".route('magic-link', ['token' => $student->access_token]);
        });

        return "🔗 روابط الدخول السحرية للطلاب\n"
            ."كل رابط أدناه خاص بطالب واحد، ويفتح حسابه مباشرة دون كلمة مرور.\n\n"
            .$lines->implode("\n\n")
            ."\n\n⚠️ تنبيه: أرسِل لكل طالب رابطه الخاص به فقط، ولا تشارك هذه الروابط مع أي شخص آخر.";
    }
}
