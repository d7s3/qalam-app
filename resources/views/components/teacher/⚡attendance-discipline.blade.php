<?php

use App\Models\Circle;
use App\Support\Scope;
use Livewire\Component;

use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Flux\Flux;

new class extends Component {
    public $fromDate;
    public $toDate;

    public $sortColumn = 'name';
    public $sortDirection = 'asc';

    public function mount()
    {
        // Default to current month
        $this->fromDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->toDate = Carbon::now()->format('Y-m-d');
    }

    public function setLastWeek()
    {
        $this->fromDate = Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');
        $this->toDate = Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d');
    }

    public function setLastMonth()
    {
        $this->fromDate = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
        $this->toDate = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
    }

    public function setThisWeek()
    {
        $this->fromDate = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->toDate = Carbon::now()->format('Y-m-d');
    }

    public function setThisMonth()
    {
        $this->fromDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->toDate = Carbon::now()->format('Y-m-d');
    }

    public function toggleSort($column)
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            // Name defaults to asc, counts default to desc
            $this->sortDirection = $column === 'name' ? 'asc' : 'desc';
        }
    }

    public function with()
    {
        $teacher = Auth::guard('teacher')->user();
        $circleIds = Scope::forRoute()->applyToCircles(Circle::query())->pluck('circles.id');

        // Main table students based on selected date range
        $students = Student::whereIn('circle_id', $circleIds)
            ->with([
                'attendances' => function ($q) {
                    if ($this->fromDate && $this->toDate) {
                        $q->whereBetween('date', [$this->fromDate, $this->toDate]);
                    }
                }
            ])
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'present_count' => $student->attendances->where('status', 'present')->count(),
                    'absent_count' => $student->attendances->where('status', 'absent')->count(),
                    'late_count' => $student->attendances->where('status', 'late')->count(),
                ];
            });

        if ($this->sortDirection === 'asc') {
            $students = $students->sortBy($this->sortColumn);
        } else {
            $students = $students->sortByDesc($this->sortColumn);
        }

        // Indicators for the last 30 days
        $last30Start = Carbon::now()->subDays(30)->format('Y-m-d');
        $last30End = Carbon::now()->format('Y-m-d');
        
        $studentsLast30Days = Student::whereIn('circle_id', $circleIds)
            ->with(['attendances' => function($q) use ($last30Start, $last30End) {
                $q->whereBetween('date', [$last30Start, $last30End]);
            }])
            ->get()
            ->map(function ($student) {
                return [
                    'name' => $student->name,
                    'present_count' => $student->attendances->where('status', 'present')->count(),
                    'absent_count' => $student->attendances->where('status', 'absent')->count(),
                    'late_count' => $student->attendances->where('status', 'late')->count(),
                ];
            });

        $frequentLate = $studentsLast30Days->where('late_count', '>', 2)->sortByDesc('late_count')->values();
        $frequentAbsent = $studentsLast30Days->where('absent_count', '>', 2)->sortByDesc('absent_count')->values();
        $topAttendance = $studentsLast30Days->where('present_count', '>', 0)->sortByDesc('present_count')->take(5)->values();

        return [
            'students' => $students->values(),
            'frequentLate' => $frequentLate,
            'frequentAbsent' => $frequentAbsent,
            'topAttendance' => $topAttendance,
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('متابعة الانضباط الحضوري') }}</flux:heading>
            <flux:subheading>{{ __('متابعة وتدقيق أيام حضور وغياب طلاب حلقتك، مع إمكانية الفرز حسب التاريخ.') }}
            </flux:subheading>
        </div>
    </div>

    <!-- Indicators Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Frequent Late -->
        <flux:card>
            <flux:heading size="sm" class="mb-4">{{ __('تجاوزوا التأخر المسموح (آخر 30 يوم)') }}</flux:heading>
            <div class="space-y-3">
                @forelse($frequentLate as $student)
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium truncate">{{ $student['name'] }}</span>
                        <flux:badge color="amber" size="sm">{{ $student['late_count'] }}</flux:badge>
                    </div>
                @empty
                    <div class="text-xs text-zinc-500 text-center py-2">{{ __('لا يوجد طلاب تجاوزوا حد التأخر.') }}</div>
                @endforelse
            </div>
            <div class="text-xs text-zinc-400 mt-4 text-center">{{ __('* أكثر من مرتين') }}</div>
        </flux:card>

        <!-- Frequent Absent -->
        <flux:card>
            <flux:heading size="sm" class="mb-4">{{ __('تجاوزوا الغياب المسموح (آخر 30 يوم)') }}</flux:heading>
            <div class="space-y-3">
                @forelse($frequentAbsent as $student)
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium truncate">{{ $student['name'] }}</span>
                        <flux:badge color="red" size="sm">{{ $student['absent_count'] }}</flux:badge>
                    </div>
                @empty
                    <div class="text-xs text-zinc-500 text-center py-2">{{ __('لا يوجد طلاب تجاوزوا حد الغياب.') }}</div>
                @endforelse
            </div>
            <div class="text-xs text-zinc-400 mt-4 text-center">{{ __('* أكثر من مرتين') }}</div>
        </flux:card>

        <!-- Top Attendance -->
        <flux:card>
            <flux:heading size="sm" class="mb-4">{{ __('الأفضل حضوراً (آخر 30 يوم)') }}</flux:heading>
            <div class="space-y-3">
                @forelse($topAttendance as $student)
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium truncate">{{ $student['name'] }}</span>
                        <flux:badge color="green" size="sm">{{ $student['present_count'] }}</flux:badge>
                    </div>
                @empty
                    <div class="text-xs text-zinc-500 text-center py-2">{{ __('لا يوجد بيانات كافية.') }}</div>
                @endforelse
            </div>
            <div class="text-xs text-zinc-400 mt-4 text-center">{{ __('* أعلى 5 طلاب') }}</div>
        </flux:card>
    </div>

    <!-- Filters Map -->
    <flux:card>
        <div class="flex flex-col md:flex-row items-end gap-4">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-48">
                    <livewire:shared.hijri-datepicker wire:model.live="fromDate" label="{{ __('من تاريخ') }}" />
                </div>
                <div class="w-full sm:w-48">
                    <livewire:shared.hijri-datepicker wire:model.live="toDate" label="{{ __('إلى تاريخ') }}" />
                </div>
            </div>
            <div class="w-full md:w-1/3 flex flex-wrap gap-2">
                <flux:button wire:click="setLastWeek" size="sm" variant="subtle">{{ __('الأسبوع الماضي') }}
                </flux:button>
                <flux:button wire:click="setLastMonth" size="sm" variant="subtle">{{ __('الشهر الماضي') }}</flux:button>
                <flux:button wire:click="setThisWeek" size="sm" variant="subtle">{{ __('هذا الأسبوع') }}</flux:button>
                <flux:button wire:click="setThisMonth" size="sm" variant="subtle">{{ __('هذا الشهر') }}</flux:button>
            </div>
        </div>
    </flux:card>

    <flux:card class="p-0 overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortColumn === 'name'" :direction="$sortDirection"
                    wire:click="toggleSort('name')">{{ __('اسم الطالب') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortColumn === 'present_count'" :direction="$sortDirection"
                    wire:click="toggleSort('present_count')">{{ __('مرات الحضور') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortColumn === 'absent_count'" :direction="$sortDirection"
                    wire:click="toggleSort('absent_count')">{{ __('مرات الغياب') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortColumn === 'late_count'" :direction="$sortDirection"
                    wire:click="toggleSort('late_count')">{{ __('مرات التأخر') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($students as $student)
                    <flux:table.row>
                        <flux:table.cell class="font-medium whitespace-nowrap">{{ $student['name'] }}</flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            <flux:badge color="green" size="sm">{{ $student['present_count'] }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            <flux:badge color="red" size="sm">{{ $student['absent_count'] }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3" >
                            <flux:badge color="amber" size="sm">{{ $student['late_count'] }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500 py-8">
                            {{ __('لا يوجد طلاب مضافين في هذه الحلقة.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>