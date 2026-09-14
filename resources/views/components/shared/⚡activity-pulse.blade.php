<?php

use App\Services\AcademyActivity;
use App\Support\Scope;
use Carbon\CarbonImmutable;
use Livewire\Component;

/**
 * What the academy did, on one panel.
 *
 * Six figures were being asked for and each lived in its own corner of the
 * database: lessons held, hours taught, who attended, who was present today,
 * verses recited, pages read. Separately each is a number; together they are a
 * sentence — and the relations along the bottom are why they share a panel.
 *
 * The reach is the reader's own, so a teacher's panel is his cohorts and a
 * manager's is the academy, and the figures need no per-role variant.
 */
new class extends Component
{
    public string $span = 'today';

    public function setSpan(string $span): void
    {
        $this->span = array_key_exists($span, self::SPANS) ? $span : 'today';
    }

    /** The stretches of days a reader asks about, and what each is called. */
    private const SPANS = [
        'today' => 'اليوم',
        'yesterday' => 'أمس',
        'week' => 'هذا الأسبوع',
        'month' => 'هذا الشهر',
    ];

    public function with(): array
    {
        $now = CarbonImmutable::now('Asia/Riyadh');

        [$from, $to] = match ($this->span) {
            'yesterday' => [$now->subDay(), $now->subDay()],
            'week' => [$now->startOfWeek(CarbonImmutable::SATURDAY), $now],
            'month' => [$now->startOfMonth(), $now],
            default => [$now, $now],
        };

        return [
            'spans' => self::SPANS,
            'from' => $from,
            'to' => $to,
            'figures' => (new AcademyActivity(Scope::forRoute(), $from, $to))->all(),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <x-section-heading icon="chart-bar">
            {{ __('نبض النشاط') }}
            <x-slot:detail>
                <x-hijri-date :date="$from" />
                @if ($from->toDateString() !== $to->toDateString())
                    →
                    <x-hijri-date :date="$to" />
                @endif
            </x-slot:detail>
        </x-section-heading>

        <div class="flex flex-wrap items-center gap-2">
            @foreach ($spans as $key => $label)
                <button type="button" wire:click="setSpan('{{ $key }}')"
                    @class([
                        'rounded-lg border px-3 py-1.5 text-xs font-semibold transition-all',
                        'bg-maroon border-maroon text-white shadow-sm' => $span === $key,
                        'border-zinc-200 text-zinc-600 hover:border-maroon/40 dark:border-zinc-700 dark:text-zinc-300' => $span !== $key,
                    ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- The six that were asked for. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['الدروس المقامة', $figures['lessons_held'], 'academic-cap', 'درس'],
            ['ساعات الدروس', $figures['lesson_hours'], 'clock', 'ساعة'],
            ['الحضور', $figures['attended'], 'user-group', 'حضور'],
            ['الطلاب الحاضرون', $figures['students_reached'], 'users', 'طالب'],
            ['الأبيات المسمَّعة', $figures['verses_recited'], 'microphone', 'بيت'],
            ['الصفحات المقروءة', $figures['pages_read'], 'book-open', 'صفحة'],
        ] as [$label, $value, $icon, $unit])
            <div class="rounded-2xl border border-zinc-100 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center gap-2 text-zinc-400">
                    <flux:icon :icon="$icon" class="size-4" />
                    <span class="text-[11px] font-semibold">{{ __($label) }}</span>
                </div>
                <div class="mt-2 flex items-baseline gap-1.5">
                    <span class="font-sans text-2xl font-extrabold text-maroon dark:text-white">{{ $value }}</span>
                    <span class="text-[11px] text-zinc-400">{{ __($unit) }}</span>
                </div>
            </div>
        @endforeach
    </div>

    {{-- And what they say about each other, which no one of them says alone. --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['نسبة الحضور', $figures['attendance_rate'], '٪', 100],
            ['متوسط الحاضرين لكل درس', $figures['attended_per_lesson'], 'طالب', 1],
            ['صفحات لكل حاضر', $figures['pages_per_attendee'], 'صفحة', 1],
            ['أبيات لكل حاضر', $figures['verses_per_attendee'], 'بيت', 1],
        ] as [$label, $value, $unit, $times])
            <div class="rounded-2xl border border-gold/25 bg-gold/[0.06] p-4">
                <span class="text-[11px] font-semibold text-zinc-500">{{ __($label) }}</span>
                <div class="mt-1.5 flex items-baseline gap-1.5">
                    {{-- Nothing to divide by is silence, not a score of zero:
                         «٠ صفحة لكل حاضر» on a day nobody came reads as a
                         verdict on students who were never there. --}}
                    @if ($value === null)
                        <span class="text-sm text-zinc-400">{{ __('لا قياس بعد') }}</span>
                    @else
                        <span class="font-sans text-xl font-extrabold text-maroon dark:text-white">
                            {{ $times === 100 ? round($value * 100) : $value }}
                        </span>
                        <span class="text-[11px] text-zinc-400">{{ __($unit) }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
