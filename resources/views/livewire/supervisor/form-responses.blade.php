<div class="space-y-6" x-data="{ activeTab: @entangle('activeTab') }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('supervisor.forms')">النماذج والاستمارات</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>الردود والإحصائيات</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white mt-2">{{ $form->title }}</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">عرض وتصفية الردود الواردة، وبناء التقارير البيانية وربط البيانات بالطلاب.</p>
        </div>
        <div class="flex gap-2">
            <flux:button as="a" :href="route('supervisor.forms')" variant="ghost" icon="chevron-right">العودة للنماذج</flux:button>
            @if($form->is_public_report)
                <flux:button size="sm" variant="ghost" icon="share" class="text-xs text-indigo-500 hover:text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 border border-zinc-200 dark:border-zinc-800" 
                    x-data="{ copied: false }"
                    x-on:click="
                        navigator.clipboard.writeText('{{ route('forms.report', [$form->slug, $form->public_report_token]) }}');
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                        $dispatch('toast', { message: 'تم نسخ رابط التقرير العام بنجاح', variant: 'success' })
                    "
                    ::title="copied ? 'تم النسخ!' : 'نسخ رابط التقرير العام'"
                >
                    رابط التقرير العام
                </flux:button>
            @endif
            <flux:button wire:click="openBulkModal" variant="primary" icon="users" class="bg-accent hover:bg-accent/90 text-white border-0">
                إضافة كافة الردود كطلاب
            </flux:button>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800">
        <button @click="activeTab = 'responses'" :class="activeTab === 'responses' ? 'border-accent text-accent' : 'border-transparent text-zinc-500 hover:text-zinc-700'" class="py-3 px-6 font-semibold text-sm border-b-2 -mb-px transition-colors">
            جدول الردود
        </button>
        <button @click="activeTab = 'reports'" :class="activeTab === 'reports' ? 'border-accent text-accent' : 'border-transparent text-zinc-500 hover:text-zinc-700'" class="py-3 px-6 font-semibold text-sm border-b-2 -mb-px transition-colors">
            التقرير البياني والتحليلي
        </button>
    </div>

    <!-- Responses Tab -->
    <div x-show="activeTab === 'responses'" class="space-y-4">
        <!-- Search bar -->
        <div class="flex gap-4 bg-white dark:bg-zinc-900 p-4 rounded-xl border border-zinc-200 dark:border-zinc-800">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="البحث في الردود والإجابات..." class="flex-1" icon="magnifying-glass" />
        </div>

        <!-- Responses Table -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-x-auto shadow-xs">
            @if($responses->isEmpty())
                <div class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                    لا توجد ردود مطابقة للبحث حالياً.
                </div>
            @else
                <table class="w-full text-start border-collapse text-sm">
                    <thead>
                        <tr class="bg-zinc-50 dark:bg-zinc-950 text-zinc-700 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-800">
                            <th class="p-4 text-start">تاريخ الرد</th>
                            @foreach($form->fields as $field)
                                <th class="p-4 text-start min-w-[120px]">{{ $field['label'] }}</th>
                            @endforeach
                            <th class="p-4 text-start">الحالة / الربط</th>
                            <th class="p-4 text-start">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-150 dark:divide-zinc-850">
                        @foreach($responses as $response)
                            <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-950/20 text-zinc-850 dark:text-zinc-200">
                                <td class="p-4 whitespace-nowrap text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ $response->created_at->format('Y-m-d H:i') }}
                                </td>
                                @foreach($form->fields as $field)
                                    @php
                                        $fieldId = $field['id'];
                                        $answer = $response->answers[$fieldId] ?? null;
                                    @endphp
                                    <td class="p-4">
                                        @if($field['type'] === 'image' && $answer)
                                            <a href="{{ asset('storage/' . $answer) }}" target="_blank" class="block w-10 h-10 rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-800 hover:scale-105 transition-transform">
                                                <img src="{{ asset('storage/' . $answer) }}" class="w-full h-full object-cover" />
                                            </a>
                                        @elseif($field['type'] === 'multiselect' && is_array($answer))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($answer as $opt)
                                                    <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-medium">
                                                        {{ $opt }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="line-clamp-2" title="{{ is_array($answer) ? implode(', ', $answer) : $answer }}">
                                                {{ is_array($answer) ? implode(', ', $answer) : $answer }}
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="p-4 whitespace-nowrap">
                                    @if($response->student_id)
                                        <div class="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400">
                                            <flux:icon name="check-circle" class="size-4 shrink-0" />
                                            <span class="font-medium">مرتبط بـ: {{ $response->student->name }}</span>
                                        </div>
                                    @else
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                            غير معالج
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4 whitespace-nowrap flex items-center gap-2">
                                    @if(!$response->student_id)
                                        <flux:button wire:click="openCreateModal({{ $response->id }})" size="sm" variant="filled" class="text-xs">
                                            إنشاء حساب طالب
                                        </flux:button>
                                        <flux:button wire:click="openLinkModal({{ $response->id }})" size="sm" variant="ghost" class="text-xs">
                                            ربط بطالب قائم
                                        </flux:button>
                                    @endif
                                    <flux:button wire:click="deleteResponse({{ $response->id }})" wire:confirm="هل أنت متأكد من رغبتك في حذف هذا الرد؟" size="sm" variant="ghost" icon="trash" class="text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <!-- Reports Tab -->
    <div x-show="activeTab === 'reports'" class="space-y-6">
        @if(empty($reportsData))
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-8 text-center text-zinc-500 dark:text-zinc-400">
                النموذج لا يحتوي على حقول من نوع "خيارات" أو "خيارات متعددة" لعرض تقارير بيانية لها.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($reportsData as $report)
                    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-5 space-y-4">
                        <h3 class="font-bold text-zinc-900 dark:text-white border-b border-zinc-100 dark:border-zinc-800 pb-3">
                            {{ $report['label'] }}
                        </h3>
                        
                        <div class="space-y-4">
                            @foreach($report['options'] as $option => $count)
                                @php
                                    $pct = $report['total'] > 0 ? round(($count / $report['total']) * 100, 1) : 0;
                                @endphp
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                        <span>{{ $option }}</span>
                                        <span>{{ $count }} رد ({{ $pct }}%)</span>
                                    </div>
                                    <div class="w-full bg-zinc-100 dark:bg-zinc-800 h-3 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500" style="background-color: {{ $form->color }}; width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Modal: Create Student Account -->
    <flux:modal wire:model="showCreateModal" class="w-full max-w-md space-y-4">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-white">إنشاء حساب طالب جديد</h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">سيتم إنشاء حساب طالب جديد في النظام ووضعه بالحالة (تحت التسجيل).</p>
        </div>

        <flux:field>
            <flux:label>اسم الطالب المعتمد *</flux:label>
            <flux:input wire:model="newStudentName" />
            <flux:error name="newStudentName" />
        </flux:field>

        <flux:field>
            <flux:label>اسم المستخدم / البريد الإلكتروني *</flux:label>
            <flux:input wire:model="newStudentUsername" placeholder="مثال: ahmad_ali" />
            <flux:error name="newStudentUsername" />
        </flux:field>

        <flux:field>
            <flux:label>كلمة المرور الافتراضية *</flux:label>
            <flux:input type="text" wire:model="newStudentPassword" />
            <flux:error name="newStudentPassword" />
        </flux:field>

        <flux:field>
            <flux:label>الحلقة المستهدفة (اختياري)</flux:label>
            <select wire:model="newStudentCircleId" class="block w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm">
                <option value="">-- بلا حلقة --</option>
                @foreach($circles as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->stage->name }})</option>
                @endforeach
            </select>
            <flux:error name="newStudentCircleId" />
        </flux:field>

        <div class="flex justify-end gap-3 pt-4 border-t border-zinc-150 dark:border-zinc-850">
            <flux:button @click="$wire.showCreateModal = false" variant="ghost">إلغاء</flux:button>
            <flux:button wire:click="createStudentAccount" variant="primary">حفظ وإنشاء الحساب</flux:button>
        </div>
    </flux:modal>

    <!-- Modal: Link to Existing Student -->
    <flux:modal wire:model="showLinkModal" class="w-full max-w-md space-y-4">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-white">ربط الرد بطالب قائم</h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">اختر الطالب من حلقاتك لربط هذا الرد ببياناته.</p>
        </div>

        <flux:field>
            <flux:label>اختر الطالب من القائمة *</flux:label>
            <select wire:model="linkStudentId" class="block w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm">
                <option value="">-- اختر طالباً --</option>
                @foreach($students as $st)
                    <option value="{{ $st->id }}">{{ $st->name }}</option>
                @endforeach
            </select>
            <flux:error name="linkStudentId" />
        </flux:field>

        <flux:field>
            <flux:label>الاسم المعتمد للطالب *</flux:label>
            <div class="space-y-2">
                <label class="flex items-center gap-2 cursor-pointer text-sm">
                    <input type="radio" wire:model="linkNameOption" value="existing" class="text-accent focus:ring-accent" />
                    <span>الاحتفاظ بالاسم الحالي للطالب في النظام</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-sm">
                    <input type="radio" wire:model="linkNameOption" value="response" class="text-accent focus:ring-accent" />
                    <span>تعديل اسم الطالب في النظام إلى الاسم الوارد في الاستمارة</span>
                </label>
            </div>
            <flux:error name="linkNameOption" />
        </flux:field>

        <div class="flex justify-end gap-3 pt-4 border-t border-zinc-150 dark:border-zinc-850">
            <flux:button @click="$wire.showLinkModal = false" variant="ghost">إلغاء</flux:button>
            <flux:button wire:click="linkToExistingStudent" variant="primary">ربط البيانات</flux:button>
        </div>
    </flux:modal>

    <!-- Modal: Bulk Create Confirmation -->
    <flux:modal wire:model="showBulkModal" class="w-full max-w-md space-y-4">
        <div>
            <h2 class="text-lg font-bold text-zinc-900 dark:text-white">الإنشاء الجماعي لحسابات الطلاب</h2>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">سيتم معالجة جميع الردود غير المرتبطة وإنشاء حسابات طلاب جديدة لها تلقائياً بالحالة (تحت التسجيل) وبكلمة مرور افتراضية (password).</p>
        </div>

        <flux:field>
            <flux:label>تعيينهم في الحلقة المستهدفة (اختياري)</flux:label>
            <select wire:model="bulkCircleId" class="block w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm">
                <option value="">-- بلا حلقة --</option>
                @foreach($circles as $c)
                    <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->stage->name }})</option>
                @endforeach
            </select>
            <flux:error name="bulkCircleId" />
        </flux:field>

        <div class="flex justify-end gap-3 pt-4 border-t border-zinc-150 dark:border-zinc-850">
            <flux:button @click="$wire.showBulkModal = false" variant="ghost">إلغاء</flux:button>
            <flux:button wire:click="bulkCreateStudents" variant="primary" class="bg-accent hover:bg-accent/90 text-white border-0">تأكيد الإنشاء الجماعي</flux:button>
        </div>
    </flux:modal>
</div>
