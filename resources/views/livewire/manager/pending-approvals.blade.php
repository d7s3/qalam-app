<div dir="rtl" class="space-y-6">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">طلبات التسجيل</flux:heading>
        <flux:subheading>الرئيسية / طلبات التسجيل</flux:subheading>
    </div>

    {{-- بطاقات الإحصائيات --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex items-center justify-between">
            <div>
                <div class="text-2xl font-black text-red-600">{{ $this->counts['rejected'] }}</div>
                <div class="text-xs text-zinc-500 mt-1">مرفوضة</div>
            </div>
            <div class="size-10 rounded-full bg-red-50 dark:bg-red-900/20 flex items-center justify-center text-red-500">
                <flux:icon icon="x-mark" class="size-5" />
            </div>
        </div>
        <div class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex items-center justify-between">
            <div>
                <div class="text-2xl font-black text-emerald-600">{{ $this->counts['approved'] }}</div>
                <div class="text-xs text-zinc-500 mt-1">مقبولة</div>
            </div>
            <div class="size-10 rounded-full bg-emerald-50 dark:bg-emerald-900/20 flex items-center justify-center text-emerald-500">
                <flux:icon icon="check" class="size-5" />
            </div>
        </div>
        <div class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex items-center justify-between">
            <div>
                <div class="text-2xl font-black text-amber-600">{{ $this->counts['pending'] }}</div>
                <div class="text-xs text-zinc-500 mt-1">بانتظار المراجعة</div>
            </div>
            <div class="size-10 rounded-full bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center text-amber-500">
                <flux:icon icon="clock" class="size-5" />
            </div>
        </div>
        <div class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex items-center justify-between">
            <div>
                <div class="text-2xl font-black text-zinc-800 dark:text-zinc-100">{{ $this->counts['total'] }}</div>
                <div class="text-xs text-zinc-500 mt-1">إجمالي الطلبات</div>
            </div>
            <div class="size-10 rounded-full bg-zinc-100 dark:bg-zinc-800 flex items-center justify-center text-zinc-500">
                <flux:icon icon="user-group" class="size-5" />
            </div>
        </div>
    </div>

    {{-- البحث والفلترة --}}
    <div class="flex flex-col sm:flex-row gap-3">
        <flux:input wire:model.live.debounce.400ms="search" placeholder="ابحث عن اسم أو بريد إلكتروني..." icon="magnifying-glass" class="flex-1" />
        <flux:select wire:model.live="statusFilter" class="sm:w-56">
            <flux:select.option value="all">كل الحالات</flux:select.option>
            <flux:select.option value="pending">قيد المراجعة</flux:select.option>
            <flux:select.option value="approved">تمت الموافقة</flux:select.option>
            <flux:select.option value="rejected">تم الرفض</flux:select.option>
        </flux:select>
    </div>

    {{-- الجدول --}}
    <div class="overflow-x-auto">
        <flux:table :paginate="$this->pendingRows">
            <flux:table.columns>
                <flux:table.column>الاسم</flux:table.column>
                <flux:table.column>البريد الإلكتروني</flux:table.column>
                <flux:table.column>رقم الجوال</flux:table.column>
                <flux:table.column>نوع الحساب</flux:table.column>
                <flux:table.column>تاريخ الطلب</flux:table.column>
                <flux:table.column>الحالة</flux:table.column>
                <flux:table.column>الإجراءات</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->pendingRows as $row)
                    @php
                        $roleLabels = \App\Livewire\Manager\PendingApprovals::ROLE_LABELS;
                        $isPending = ! $row->is_approved && ! $row->is_rejected;
                    @endphp
                    <flux:table.row :key="$row->account_type.'-'.$row->id">
                        <flux:table.cell class="font-bold text-zinc-900 dark:text-white">{{ $row->name }}</flux:table.cell>
                        <flux:table.cell>{{ $row->email }}</flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap" dir="ltr">{{ $row->phone }}</flux:table.cell>
                        <flux:table.cell>
                            @if($isPending && $row->account_type === 'student')
                                <flux:select wire:model="reassignType.{{ $row->account_type }}-{{ $row->id }}" size="sm" class="w-32">
                                    @foreach($roleLabels as $key => $label)
                                        <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:badge color="zinc" size="sm">{{ $roleLabels[$row->account_type] ?? $row->account_type }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap text-xs text-zinc-500">
                            {{ \Illuminate\Support\Carbon::parse($row->created_at)->translatedFormat('Y-m-d H:i') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($row->is_rejected)
                                <flux:badge color="red" size="sm">تم الرفض</flux:badge>
                            @elseif($row->is_approved)
                                <flux:badge color="emerald" size="sm">تمت الموافقة</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm" icon="clock">قيد المراجعة</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($isPending)
                                <div class="flex items-center gap-2">
                                    <flux:button size="sm" variant="primary"
                                        wire:click="approve({{ $row->id }}, '{{ $row->account_type }}')"
                                        class="bg-emerald-600 hover:bg-emerald-700 border-none px-3">
                                        قبول
                                    </flux:button>
                                    <flux:button size="sm" variant="danger"
                                        wire:click="reject({{ $row->id }}, '{{ $row->account_type }}')"
                                        wire:confirm="هل أنت متأكد من رفض هذا الطلب؟" class="px-3">
                                        رفض
                                    </flux:button>
                                </div>
                            @else
                                <span class="text-zinc-300 dark:text-zinc-700">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon icon="check-circle" class="size-10 text-emerald-500/20" />
                                <flux:text class="text-zinc-400">لا توجد طلبات مطابقة</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</div>
