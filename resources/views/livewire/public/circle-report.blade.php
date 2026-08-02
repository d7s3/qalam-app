<div class="w-full max-w-7xl mx-auto space-y-6 px-2 md:px-6">
    {{-- Accent top bar --}}
    <div class="h-2 w-full rounded-full bg-emerald-500"></div>

    {{-- Header --}}
    <div class="bg-white rounded-2xl border border-zinc-200 p-6 md:p-8 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-zinc-900">
                    تقرير الإنجاز — {{ $scopeName }}
                </h1>
                <p class="text-sm md:text-base text-zinc-500 mt-2">
                    تقرير إنجاز الطلاب في الحفظ والمراجعة والحضور والمتون والمنظومات.
                </p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                    <flux:icon icon="calendar" class="size-3.5" />
                    <x-hijri-date :date="$from" /> — <x-hijri-date :date="$to" />
                </span>
                @if($selectedStudent)
                    <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">
                        <flux:icon icon="user" class="size-3.5" />
                        الطالب: {{ $selectedStudent->name }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Report body --}}
    <x-reports.circle-summary :report="$report" :show-circle-column="$showCircleColumn" />
</div>
