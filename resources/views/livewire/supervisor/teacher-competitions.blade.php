<div class="space-y-6" dir="rtl">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon icon="trophy" />
            </div>
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">مسابقة المعلمين</flux:heading>
                <flux:subheading>أنشئ مسابقات لتقييم المعلمين وفق بنود من اختيارك</flux:subheading>
            </div>
        </div>
        <flux:button variant="primary" size="sm" icon="plus" wire:click="create">إنشاء مسابقة جديدة</flux:button>
    </div>

    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs overflow-hidden">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>اسم المسابقة</flux:table.column>
                <flux:table.column class="hidden md:table-cell">الفترة</flux:table.column>
                <flux:table.column class="text-center">المعلمون</flux:table.column>
                <flux:table.column class="text-center">الحالة</flux:table.column>
                <flux:table.column class="w-10"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($competitions as $competition)
                    <flux:table.row :key="$competition->id">
                        <flux:table.cell class="font-bold text-zinc-900 dark:text-white">
                            <a href="{{ route('supervisor.teacher-competitions.manage', $competition->id) }}"
                                class="hover:text-emerald-600 dark:hover:text-emerald-400 hover:underline underline-offset-4">
                                {{ $competition->name }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell text-sm text-zinc-500">
                            <x-hijri-date :date="$competition->start_date" /> - <x-hijri-date :date="$competition->end_date" />
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            <flux:badge size="sm" variant="neutral">{{ $competition->participants_count }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-center">
                            @if($competition->isCurrentlyActive())
                                <flux:badge size="sm" color="green">نشطة</flux:badge>
                            @elseif($competition->is_active)
                                <flux:badge size="sm" color="amber">لم تبدأ / انتهى موعدها</flux:badge>
                            @else
                                <flux:badge size="sm" variant="neutral">منتهية</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="first:ps-3">
                            <div class="flex items-center gap-1">
                                <flux:button size="sm" variant="ghost" icon="cog-6-tooth" :href="route('supervisor.teacher-competitions.manage', $competition->id)" title="إدارة المسابقة" />
                                <flux:button size="sm" variant="ghost" :icon="$competition->is_active ? 'pause-circle' : 'play-circle'" wire:click="toggleActive({{ $competition->id }})" title="{{ $competition->is_active ? 'إنهاء المسابقة' : 'تفعيل المسابقة' }}" />
                                <flux:button size="sm" variant="ghost" class="text-red-500 hover:text-red-700 hover:bg-red-50" icon="trash" wire:click="delete({{ $competition->id }})" wire:confirm="هل أنت متأكد من حذف هذه المسابقة نهائيًا؟ سيتم حذف كل البنود والدرجات المرتبطة بها." />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center py-16">
                            <flux:text class="text-zinc-400">لا توجد مسابقات معلمين بعد</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Create Competition Modal --}}
    <flux:modal name="teacher-competition-modal" class="md:w-[420px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">إنشاء مسابقة معلمين جديدة</flux:heading>
                <flux:subheading>حدد اسم المسابقة وفترة انعقادها، وبعد إنشائها تقدر تضيف المعلمين المشاركين وبنود التقييم.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input label="اسم المسابقة" wire:model="name" placeholder="مثال: مسابقة التميز التعليمي" required />
                <livewire:shared.hijri-datepicker wire:model="start_date" label="تاريخ البداية (هجري)" />
                <livewire:shared.hijri-datepicker wire:model="end_date" label="تاريخ النهاية (هجري)" />
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost" wire:click="cancel">إلغاء</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">إنشاء</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
