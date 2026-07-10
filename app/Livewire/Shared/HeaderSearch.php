<?php

namespace App\Livewire\Shared;

use App\Models\Circle;
use App\Models\Student;
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

    public function studentUrl(Student $student): string
    {
        if (auth()->guard('teacher')->check()) {
            return route('teacher.student-recitation-log', $student->id);
        }

        if (auth()->guard('guardian')->check()) {
            return route('guardian.student', $student->id);
        }

        if (auth()->guard('supervisor')->check()) {
            return route('supervisor.students');
        }

        return route('manager.students');
    }

    public function circleUrl(Circle $circle): string
    {
        if (auth()->guard('supervisor')->check()) {
            return route('supervisor.circles');
        }

        return route('manager.circles');
    }

    private function studentQuery(): Builder
    {
        if (auth()->guard('student')->check()) {
            return Student::query()->whereRaw('1 = 0');
        }

        $query = Student::query()->where('is_approved', true);

        if (auth()->guard('teacher')->check()) {
            $circleIds = auth()->guard('teacher')->user()->circles()->pluck('circles.id');
            $query->whereIn('circle_id', $circleIds);
        } elseif (auth()->guard('supervisor')->check()) {
            $stageIds = auth()->guard('supervisor')->user()->stages()->pluck('stages.id');
            $query->whereHas('circle', fn (Builder $q) => $q->whereIn('stage_id', $stageIds));
        } elseif (auth()->guard('guardian')->check()) {
            $query->where('guardian_id', auth()->guard('guardian')->id());
        }

        return $query;
    }

    private function circleQuery(): Builder
    {
        if (auth()->guard('guardian')->check() || auth()->guard('student')->check()) {
            return Circle::query()->whereRaw('1 = 0');
        }

        $query = Circle::query();

        if (auth()->guard('teacher')->check()) {
            $circleIds = auth()->guard('teacher')->user()->circles()->pluck('circles.id');
            $query->whereIn('id', $circleIds);
        } elseif (auth()->guard('supervisor')->check()) {
            $stageIds = auth()->guard('supervisor')->user()->stages()->pluck('stages.id');
            $query->whereIn('stage_id', $stageIds);
        }

        return $query;
    }

    public function render()
    {
        return view('livewire.shared.header-search');
    }
}
