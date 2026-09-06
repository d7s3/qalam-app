<?php

namespace App\Livewire\Shared;

use App\Models\Circle;
use App\Models\Student;
use App\Support\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;

class HeaderSearch extends Component
{
    public string $query = '';

    /** @return array{students: Collection<int, Student>, circles: Collection<int, Circle>} */
    public function results(): array
    {
        $term = trim($this->query);

        if (mb_strlen($term) < 2) {
            return ['students' => collect(), 'circles' => collect()];
        }

        return [
            'students' => $this->studentQuery()->where('name', 'like', "%{$term}%")->limit(5)->get(),
            'circles' => $this->circleQuery()->where('name', 'like', "%{$term}%")->limit(5)->get(),
        ];
    }

    /**
     * Where a found student is opened, from the page the searcher is on rather
     * than from whichever guard answers first — a reader may hold two roles, and
     * the header sits on every page of both.
     */
    public function studentUrl(Student $student): string
    {
        return match (Scope::forRoute()->role()) {
            'teacher' => route('teacher.student-recitation-log', $student->id),
            'guardian' => route('guardian.student', $student->id),
            'supervisor' => route('supervisor.students'),
            default => route('manager.students'),
        };
    }

    public function circleUrl(Circle $circle): string
    {
        return Scope::forRoute()->role() === 'supervisor'
            ? route('supervisor.circles')
            : route('manager.circles');
    }

    private function studentQuery(): Builder
    {
        // A student searches nothing: the box is there for those who look after
        // others, and he is not one of them.
        if (Scope::forRoute()->role() === 'student') {
            return Student::query()->whereRaw('1 = 0');
        }

        return Scope::forRoute()->applyToStudents(
            Student::query()->whereRoleState(fn ($q) => $q->where('is_approved', true)),
        );
    }

    private function circleQuery(): Builder
    {
        // Neither a student nor a guardian browses cohorts.
        if (in_array(Scope::forRoute()->role(), ['student', 'guardian'], true)) {
            return Circle::query()->whereRaw('1 = 0');
        }

        return Scope::forRoute()->applyToCircles(Circle::query());
    }

    public function render()
    {
        return view('livewire.shared.header-search');
    }
}
