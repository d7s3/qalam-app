<div class="space-y-6">
    <div class="flex items-center gap-3">
        <div class="p-2 rounded-lg bg-zinc-50 text-red-500 dark:bg-zinc-800 dark:text-red-400">
            <flux:icon icon="exclamation-triangle" />
        </div>
        <div>
            <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">لائحة التجاوزات</flux:heading>
            <flux:subheading>الطلاب الذين تجاوزوا حد الغياب والتأكيد خلال آخر {{ $periodDays }} يوماً</flux:subheading>
        </div>
    </div>

    <div
        class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs overflow-hidden">
        @if ($students->isEmpty())
            <div class="p-10 text-center flex flex-col items-center">
                <div class="p-4 rounded-full bg-green-50 mb-4 dark:bg-green-900/20">
                    <flux:icon icon="check-badge" class="size-10 text-green-500" />
                </div>
                <h3 class="text-xl font-medium text-zinc-700 dark:text-zinc-300">لا توجد تجاوزات</h3>
                <p class="text-sm text-zinc-500 mt-2">لا يوجد أي طالب تجاوز حد الغياب المسموح ({{ $absenceLimit }})
                    أو التأخير ({{ $latenessLimit }}) خلال الفترة المحددة.</p>
            </div>
        @else
            <flux:table class="w-full">
                <flux:table.columns>
                    <flux:table.column>الطالب</flux:table.column>
                    <flux:table.column>الدفعة</flux:table.column>
                    <flux:table.column class="text-center">الغياب (أكثر من {{ $absenceLimit - 1 }})</flux:table.column>
                    <flux:table.column class="text-center">التأخر (أكثر من {{ $latenessLimit - 1 }})</flux:table.column>
                    <flux:table.column class="text-center">التواصل</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($students as $student)
                        <flux:table.row :key="$student->id"
                            class="{{ $student->recent_absences_count >= $absenceLimit ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}">
                            <flux:table.cell class="first:ps-3" >
                                <span class="font-bold text-zinc-900 dark:text-white">{{ $student->name }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="first:ps-3" >
                                @if ($student->circle)
                                    <flux:badge size="sm" variant="neutral">{{ $student->circle->name }}</flux:badge>
                                    <span class="text-xs text-zinc-400 block mt-1">{{ $student->circle->stage->name }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-center">
                                @if ($student->recent_absences_count >= $absenceLimit)
                                    <span
                                        class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-600 rounded-full">
                                        {{ $student->recent_absences_count }}
                                    </span>
                                @else
                                    <span class="text-zinc-500">{{ $student->recent_absences_count }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-center">
                                @if ($student->recent_lateness_count >= $latenessLimit)
                                    <span
                                        class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-amber-900 bg-amber-400 rounded-full">
                                        {{ $student->recent_lateness_count }}
                                    </span>
                                @else
                                    <span class="text-zinc-500">{{ $student->recent_lateness_count }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-center">
                                @php
                                    $msg = "نود إشعاركم بأنه تم إحالة الطالب {$student->name} للإدارة لتجاوزه الحد المسموح بالغيابات/التأخير نرجو منكم التعاون لمعرفة الأسباب.";
                                @endphp
                                @if ($student->guardian && $student->guardian->phone)
                                    <a class="inline-flex items-center justify-center p-2 rounded-lg bg-green-50 text-green-600 hover:bg-green-100   s"
                                        href="https://wa.me/{{ $student->guardian->phone }}/?text={{ urlencode($msg) }}"
                                        target="_blank" title="مراسلة ولي الأمر">
                                        <flux:icon icon="chat-bubble-left-right" class="size-5" />
                                    </a>
                                @else
                                    <button x-data="{ copied: false }" data-msg="{{ $msg }}"
                                        x-on:click="navigator.clipboard.writeText($el.dataset.msg).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                                        class="inline-flex items-center justify-center p-2 rounded-lg bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700   s"
                                        title="لا يوجد رقم - نسخ الرسالة">
                                        <flux:icon x-show="!copied" icon="clipboard-document" class="size-5" />
                                        <flux:icon x-cloak x-show="copied" icon="check" class="size-5 text-green-500" />
                                    </button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </div>
</div>