<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('supervisor.circles') }}"
                class="p-2 rounded-lg text-zinc-500 hover:text-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                <flux:icon icon="arrow-right" class="size-5" />
            </a>
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
                    تقرير الإنجاز — {{ $selectedCircle ? 'دفعة '.$selectedCircle->name : 'برنامج '.$stage->name }}
                </flux:heading>
                <flux:subheading>
                    <x-hijri-date :date="$from" /> إلى <x-hijri-date :date="$to" />
                    @if($selectedStudent)
                        · الطالب: {{ $selectedStudent->name }}
                    @endif
                </flux:subheading>
            </div>
        </div>

        <div x-data class="shrink-0">
            <flux:button variant="primary" size="sm" icon="link"
                @click="navigator.clipboard.writeText(@js($shareUrl)); $dispatch('toast', { message: 'تم نسخ رابط مشاركة التقرير', variant: 'success' })">
                نسخ رابط المشاركة
            </flux:button>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <flux:select label="الدفعة" wire:model.live="circleId">
                <flux:select.option value="">كل دفعات البرنامج</flux:select.option>
                @foreach($circles as $circle)
                    <flux:select.option value="{{ $circle->id }}">{{ $circle->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="الطالب" wire:model.live="studentId">
                <flux:select.option value="">كل الطلاب</flux:select.option>
                @foreach($students as $student)
                    <flux:select.option value="{{ $student->id }}">{{ $student->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="الفترة" wire:model.live="preset">
                @foreach($presets as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            @if($preset === 'custom')
                <div class="grid grid-cols-2 gap-2">
                    <livewire:shared.hijri-datepicker wire:model.live="fromDate" label="من (هجري)" />
                    <livewire:shared.hijri-datepicker wire:model.live="toDate" label="إلى (هجري)" />
                </div>
            @endif
        </div>
    </div>

    {{-- Report body --}}
    <x-reports.circle-summary :report="$report" :show-circle-column="! $selectedCircle" />
</div>
