<div class="space-y-6" dir="rtl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <flux:button size="sm" variant="ghost" icon="arrow-right" :href="route('supervisor.teacher-competitions')" />
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ $competition->name }}</flux:heading>
                <flux:subheading>
                    <x-hijri-date :date="$competition->start_date" /> - <x-hijri-date :date="$competition->end_date" />
                    @if($competition->isCurrentlyActive())
                        <flux:badge size="sm" color="green" class="ms-2">نشطة الآن</flux:badge>
                    @endif
                </flux:subheading>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap gap-2 border-b border-zinc-100 dark:border-zinc-800 pb-2">
        <flux:button size="sm" :variant="$activeTab === 'participants' ? 'primary' : 'ghost'" wire:click="setActiveTab('participants')">المشاركون</flux:button>
        <flux:button size="sm" :variant="$activeTab === 'criteria' ? 'primary' : 'ghost'" wire:click="setActiveTab('criteria')">بنود التقييم</flux:button>
        <flux:button size="sm" :variant="$activeTab === 'scoring' ? 'primary' : 'ghost'" wire:click="setActiveTab('scoring')">التقييم</flux:button>
        <flux:button size="sm" :variant="$activeTab === 'standings' ? 'primary' : 'ghost'" wire:click="setActiveTab('standings')">الترتيب</flux:button>
    </div>

    {{-- Participants --}}
    @if($activeTab === 'participants')
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-5 space-y-4">
            <flux:heading size="lg">المعلمون المشاركون</flux:heading>
            <flux:subheading>حدد المعلمين المشاركين في هذه المسابقة من ضمن معلمي الدفعات التابعة لك.</flux:subheading>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 max-h-96 overflow-y-auto p-2 border border-zinc-100 rounded-lg dark:border-zinc-800">
                @forelse($teachersList as $teacher)
                    <div class="flex items-center gap-2">
                        <flux:checkbox wire:model="selectedParticipants" :value="$teacher->id" :id="'ptc-'.$teacher->id" />
                        <flux:label :for="'ptc-'.$teacher->id" class="cursor-pointer">{{ $teacher->name }}</flux:label>
                    </div>
                @empty
                    <span class="text-xs text-zinc-400 col-span-full text-center py-4">لا يوجد معلمون ضمن دفعاتك</span>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" wire:click="saveParticipants">حفظ المشاركين</flux:button>
            </div>
        </div>
    @endif

    {{-- Criteria --}}
    @if($activeTab === 'criteria')
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-5 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="lg">بنود التقييم</flux:heading>
                    <flux:subheading>أضف البنود اللي هتقيّم المعلمين عليها، وحدد أقصى نقاط لكل بند.</flux:subheading>
                </div>
                @unless($competition->criteriaAreLocked())
                    <flux:button wire:click="addCriterion" variant="ghost" icon="plus">إضافة بند</flux:button>
                @endunless
            </div>

            @if($competition->criteriaAreLocked())
                <flux:callout icon="lock-closed" color="amber">
                    لا يمكن تعديل أو حذف بنود التقييم بعد بدء تسجيل الدرجات.
                </flux:callout>
            @endif

            <div class="space-y-3">
                @foreach($criteria as $index => $c)
                    <div class="p-4 rounded-xl border border-zinc-100 dark:border-zinc-800 flex flex-col md:flex-row md:items-center justify-between gap-4" wire:key="criterion-{{ $index }}">
                        <div class="flex flex-col md:flex-row md:items-center gap-4 flex-1">
                            <flux:input wire:model="criteria.{{ $index }}.name" label="اسم البند" :disabled="$competition->criteriaAreLocked()" required />
                            <flux:input type="number" wire:model="criteria.{{ $index }}.max_points" label="أقصى نقاط" min="1" :disabled="$competition->criteriaAreLocked()" required />
                        </div>
                        @unless($competition->criteriaAreLocked())
                            <flux:button wire:click="removeCriterion({{ $index }})" wire:confirm="هل أنت متأكد من حذف هذا البند؟" variant="ghost" icon="trash" class="text-red-500 hover:text-red-700" />
                        @endunless
                    </div>
                @endforeach

                @if(count($criteria) === 0)
                    <div class="text-center py-10 text-sm text-zinc-400 border border-dashed rounded-xl">لا توجد بنود تقييم بعد. أضف أول بند الآن.</div>
                @endif
            </div>

            @unless($competition->criteriaAreLocked())
                <div class="flex justify-end">
                    <flux:button variant="primary" wire:click="saveCriteria">حفظ بنود التقييم</flux:button>
                </div>
            @endunless
        </div>
    @endif

    {{-- Scoring --}}
    @if($activeTab === 'scoring')
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-5 space-y-4 overflow-x-auto">
            <flux:heading size="lg">تقييم المعلمين</flux:heading>
            <flux:subheading>أدخل درجة كل معلم على كل بند، ثم احفظ.</flux:subheading>

            @if(empty($criteria) || $competition->participants->isEmpty())
                <div class="text-center py-10 text-sm text-zinc-400 border border-dashed rounded-xl">
                    لازم تضيف مشاركين وبنود تقييم الأول قبل ما تقدر تقيّم.
                </div>
            @else
                <table class="w-full text-sm min-w-[500px]">
                    <thead>
                        <tr class="border-b border-zinc-100 dark:border-zinc-800">
                            <th class="text-start py-2 px-2">المعلم</th>
                            @foreach($competition->criteria as $criterion)
                                <th class="text-center py-2 px-2">{{ $criterion->name }} <span class="text-xs text-zinc-400">(من {{ $criterion->max_points }})</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($competition->participants as $teacher)
                            <tr class="border-b border-zinc-50 dark:border-zinc-800/60" wire:key="score-row-{{ $teacher->id }}">
                                <td class="py-2 px-2 font-bold text-zinc-800 dark:text-zinc-100">{{ $teacher->name }}</td>
                                @foreach($competition->criteria as $criterion)
                                    <td class="py-2 px-2">
                                        <flux:input
                                            type="number"
                                            wire:model="scores.{{ $teacher->id }}.{{ $criterion->id }}"
                                            min="0"
                                            max="{{ $criterion->max_points }}"
                                            class="w-20 mx-auto text-center" />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="flex justify-end">
                    <flux:button variant="primary" wire:click="saveScores">حفظ التقييمات</flux:button>
                </div>
            @endif
        </div>
    @endif

    {{-- Standings --}}
    @if($activeTab === 'standings')
        <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-5">
            <flux:heading size="lg" class="mb-4">ترتيب المعلمين</flux:heading>

            @if($this->standings->isEmpty())
                <div class="text-center py-10 text-sm text-zinc-400 border border-dashed rounded-xl">لا يوجد معلمون مشاركون بعد.</div>
            @else
                <div class="space-y-2">
                    @foreach($this->standings as $row)
                        <div class="flex items-center justify-between gap-3 p-3 rounded-xl {{ $row['rank'] <= 3 ? 'bg-amber-50 dark:bg-amber-900/20' : 'bg-zinc-50 dark:bg-zinc-800/50' }}">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-black text-sm {{ $row['rank'] <= 3 ? 'bg-amber-400 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
                                    {{ $row['rank'] }}
                                </div>
                                <span class="font-bold text-zinc-800 dark:text-zinc-100">{{ $row['teacher']->name }}</span>
                            </div>
                            <div class="text-end">
                                <div class="font-bold text-zinc-700 dark:text-zinc-200">{{ $row['score'] }} / {{ $row['max_score'] }}</div>
                                <div class="text-xs text-zinc-400">{{ $row['percentage'] }}%</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
