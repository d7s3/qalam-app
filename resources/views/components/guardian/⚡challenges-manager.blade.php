<?php

use Livewire\Component;
use App\Models\Challenge;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public function challenges()
    {
        return Challenge::where('guardian_id', Auth::guard('guardian')->id())
            ->with(['student', 'items'])
            ->latest()
            ->get();
    }

    public function cancelChallenge($id)
    {
        $challenge = Challenge::where('guardian_id', Auth::guard('guardian')->id())
            ->where('id', $id)
            ->firstOrFail();

        $challenge->delete();

        $this->dispatch('toast', variant: 'success', heading: 'تم حذف المكافأة التحفيزية بنجاح');
    }

    public function markChallengeStatus($id, $status)
    {
        $challenge = Challenge::where('guardian_id', Auth::guard('guardian')->id())
            ->where('id', $id)
            ->firstOrFail();

        $challenge->update(['status' => $status]);
        $this->dispatch('toast', variant: 'success', heading: 'تم تحديث حالة المكافأة');
    }

    public function forgiveAbsence($itemId, $attendanceId)
    {
        $item = \App\Models\ChallengeItem::whereHas('challenge', function($q) {
            $q->where('guardian_id', Auth::guard('guardian')->id());
        })->findOrFail($itemId);

        $metadata = $item->metadata ?? [];
        $ignored = $metadata['ignored_absences'] ?? [];
        if (!in_array($attendanceId, $ignored)) {
            $ignored[] = $attendanceId;
            $metadata['ignored_absences'] = $ignored;
            $item->update(['metadata' => $metadata]);
        }
        $this->dispatch('toast', variant: 'success', heading: 'تم التغاضي عن الغياب بنجاح واستئناف المكافأة');
    }

    public function with()
    {
        return [
            'groupedChallenges' => $this->challenges()->groupBy('status'),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('إدارة المكافآت التحفيزية') }}</flux:heading>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @php
            $statusLabels = [
                'active' => ['label' => 'مكافآت قائمة', 'color' => 'indigo', 'icon' => 'play'],
                'completed' => ['label' => 'تم إنجازها', 'color' => 'emerald', 'icon' => 'check-circle'],
                'failed' => ['label' => 'لم تنجز', 'color' => 'red', 'icon' => 'x-circle'],
            ];
        @endphp

        @foreach (['active', 'completed', 'failed'] as $status)
            <div class="space-y-4">
                <div class="flex items-center gap-2 px-1">
                    <flux:icon :icon="$statusLabels[$status]['icon']" class="size-5 text-{{ $statusLabels[$status]['color'] }}-500" />
                    <flux:heading size="lg">{{ __($statusLabels[$status]['label']) }}</flux:heading>
                    <flux:badge variant="subtle" size="sm" color="{{ $statusLabels[$status]['color'] }}">
                        {{ $groupedChallenges->get($status)?->count() ?? 0 }}
                    </flux:badge>
                </div>

                <div class="space-y-3">
                    @forelse ($groupedChallenges->get($status) ?? [] as $challenge)
                        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-4 shadow-sm space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center">
                                        <flux:icon icon="user" class="size-5 text-zinc-500" />
                                    </div>
                                    <div>
                                        <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $challenge->student->name }}</div>
                                        <div class="text-xs text-zinc-500">
                                            @if($challenge->start_date)
                                                {{ __('البداية: ') }} <x-hijri-date :date="$challenge->start_date" />
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                @if($status === 'active')
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            <flux:menu.item 
                                                icon="check-circle" 
                                                wire:click="markChallengeStatus({{ $challenge->id }}, 'completed')"
                                                wire:confirm="هل أنت متأكد من تحديد هذه المكافأة كمنجزة؟"
                                            >
                                                {{ __('تحديد كمنجزة') }}
                                            </flux:menu.item>
                                            <flux:menu.item 
                                                icon="x-circle" 
                                                variant="danger"
                                                wire:click="markChallengeStatus({{ $challenge->id }}, 'failed')"
                                                wire:confirm="هل أنت متأكد من تحديد هذه المكافأة كغير منجزة؟"
                                            >
                                                {{ __('تحديد كغير منجزة') }}
                                            </flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item 
                                                icon="trash" 
                                                variant="danger"
                                                wire:click="cancelChallenge({{ $challenge->id }})"
                                                wire:confirm="هل أنت متأكد من حذف هذه المكافأة؟"
                                            >
                                                {{ __('حذف المكافأة') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @else
                                    <flux:dropdown>
                                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                        <flux:menu>
                                            <flux:menu.item 
                                                icon="trash" 
                                                variant="danger"
                                                wire:click="cancelChallenge({{ $challenge->id }})"
                                                wire:confirm="هل أنت متأكد من حذف هذه المكافأة؟"
                                            >
                                                {{ __('حذف المكافأة') }}
                                            </flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </div>

                            <div class="space-y-2">
                                @foreach ($challenge->items as $item)
                                    <div class="text-sm">
                                        <div class="flex justify-between mb-1">
                                            <span class="text-zinc-600 dark:text-zinc-400">
                                                @if($item->type === 'attendance')
                                                    {{ __('مكافأة الحضور والانضباط') }}
                                                @elseif($item->type === 'recitation_days')
                                                    {{ __('الإنجاز القرآني') }}
                                                @elseif($item->type === 'exam_passed')
                                                    @php 
                                                        $examLevelId = $item->metadata['exam_level_id'] ?? null;
                                                        $examName = $examLevelId ? \App\Models\ExamLevel::find($examLevelId)?->name : null;
                                                    @endphp
                                                    {{ __('اختبار:') }} {{ $examName ?? __('مستوى الجمعية') }}
                                                @else
                                                    {{ __('بند المكافأة') }}
                                                @endif
                                            </span>
                                            <span class="font-medium">
                                                @php $calculatedProgress = $item->calculateProgress(); @endphp
                                                @if($item->type === 'attendance' || $item->type === 'recitation_days')
                                                    {{ $calculatedProgress }}/{{ $item->target_value }} {{ __('يوم') }}
                                                @elseif($item->type === 'exam_passed')
                                                    @if($calculatedProgress >= $item->target_value)
                                                        {{ __('تم الاجتياز') }} ✅
                                                    @else
                                                        {{ __('الدرجة المطلوبة') }}: {{ $item->target_value }}%
                                                    @endif
                                                @else
                                                    {{ $calculatedProgress }}/{{ $item->target_value }}
                                                @endif
                                            </span>
                                        </div>
                                        @php
                                            $progress = $item->target_value > 0 ? min(($calculatedProgress / $item->target_value) * 100, 100) : 0;
                                        @endphp
                                        <div class="h-1.5 w-full bg-zinc-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                                            <div class="h-full bg-{{ $statusLabels[$status]['color'] }}-500 rounded-full" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>

                                    @if($challenge->status === 'active' && $item->type === 'attendance')
                                        @php
                                            $unignored = $item->getUnignoredAbsences();
                                        @endphp
                                        @if($unignored->isNotEmpty())
                                            <div class="mt-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-xl p-3">
                                                <div class="flex items-start gap-2">
                                                    <flux:icon icon="exclamation-triangle" class="size-5 text-red-500 shrink-0 mt-0.5" />
                                                    <div>
                                                        <div class="text-sm font-bold text-red-800 dark:text-red-300">
                                                            {{ __('تنبيه: تم تسجيل غياب وتوقف العداد') }}
                                                        </div>
                                                        <div class="text-xs text-red-700 dark:text-red-400 mt-1">
                                                            {{ __('تغيب الطالب في يوم:') }} <x-hijri-date :date="$unignored->first()->date" />
                                                        </div>
                                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 mt-3">
                                                            <flux:button size="sm" variant="danger" wire:click="markChallengeStatus({{ $challenge->id }}, 'failed')">
                                                                {{ __('اعتبارها غير منجزة') }}
                                                            </flux:button>
                                                            <flux:button size="sm" variant="subtle" class="!bg-white dark:!bg-zinc-800 hover:!bg-zinc-50 dark:hover:!bg-zinc-700 !text-zinc-700 dark:!text-zinc-300" wire:click="forgiveAbsence({{ $item->id }}, {{ $unignored->first()->id }})">
                                                                {{ __('التغاضي وإعطاء فرصة') }}
                                                            </flux:button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                @endforeach
                            </div>

                            <div class="pt-2 border-t border-zinc-100 dark:border-zinc-800 flex items-center gap-2">
                                <flux:icon icon="gift" class="size-4 text-zinc-400" />
                                <span class="text-xs text-zinc-500 truncate">{{ $challenge->prize_description }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 bg-zinc-50/50 dark:bg-zinc-800/20 rounded-2xl border border-dashed border-zinc-200 dark:border-zinc-800">
                            <span class="text-sm text-zinc-400">{{ __('لا يوجد مكافآت مسجلة') }}</span>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
