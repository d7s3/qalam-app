<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon icon="book-open" />
            </div>
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">إدارة المنظومات العلمية والشعرية</flux:heading>
                <flux:subheading>إضافة وتعديل المنظومات وأبياتها وتسيير خطط حفظها للطلاب</flux:subheading>
            </div>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="createOde">منظومة جديدة</flux:button>
    </div>

    {{-- Main Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Odes List Sidebar / Grid --}}
        <div class="{{ $selectedOdeId ? 'lg:col-span-4' : 'lg:col-span-12' }} space-y-4">
            <div class="flex items-center gap-2">
                <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search" placeholder="بحث عن منظومة..." class="w-full" />
            </div>

            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 flex items-center justify-between">
                    <span class="font-bold text-sm text-zinc-700 dark:text-zinc-300">المنظومات المسجلة ({{ $odesList->count() }})</span>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-[600px] overflow-y-auto">
                    @forelse ($odesList as $ode)
                        <div class="p-4 flex items-start justify-between gap-4 transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 {{ $selectedOdeId === $ode->id ? 'bg-zinc-50 dark:bg-zinc-800/40 border-r-4 border-primary' : '' }}">
                            <div class="flex-1 min-w-0 cursor-pointer" wire:click="selectOde({{ $ode->id }})">
                                <flux:heading size="sm" class="font-bold truncate text-zinc-900 dark:text-white">{{ $ode->name }}</flux:heading>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-2">{{ $ode->description ?: 'لا يوجد وصف للمنظومة' }}</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <flux:badge size="sm" variant="neutral">{{ $ode->verses_count ?? $ode->verses()->count() }} بيت</flux:badge>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editOde({{ $ode->id }})" title="تعديل المنظومة" />
                                <flux:button size="sm" variant="ghost" icon="trash" color="red" wire:click="deleteOde({{ $ode->id }})" wire:confirm="هل أنت متأكد من حذف هذه المنظومة وجميع أبياتها؟" title="حذف المنظومة" />
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <flux:icon icon="book-open" class="size-8 mx-auto text-zinc-400 mb-2" />
                            <flux:text class="text-zinc-400 text-sm">لا توجد منظومات مسجلة حالياً</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Verse Management for Selected Ode --}}
        @if ($selectedOdeId)
            <div class="lg:col-span-8 space-y-6">
                @if ($selectedOde)
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-6 space-y-6">
                        {{-- Ode Info & Actions Header --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ $selectedOde->name }}</flux:heading>
                                    <flux:badge color="zinc">{{ $selectedOde->verses->count() }} بيت</flux:badge>
                                </div>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ $selectedOde->description ?: 'لا يوجد وصف لهذه المنظومة' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="filled" icon="arrow-up-on-square-stack" wire:click="openBulkImport">
                                    استيراد جماعي للأبيات
                                </flux:button>
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="selectOde(null)">
                                    إغلاق المعاينة
                                </flux:button>
                            </div>
                        </div>

                        {{-- Add Single Verse Form --}}
                        <div class="bg-zinc-50/50 dark:bg-zinc-800/20 p-4 rounded-xl border border-zinc-100 dark:border-zinc-800 space-y-4">
                            <flux:heading size="sm" class="font-bold text-zinc-800 dark:text-zinc-200">إضافة بيت جديد</flux:heading>
                            
                            <form wire:submit.prevent="saveVerse" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                <div class="md:col-span-2">
                                    <flux:field>
                                        <flux:label>رقم البيت</flux:label>
                                        <flux:input type="number" min="1" wire:model="newVerseNumber" required />
                                        <flux:error name="newVerseNumber" />
                                    </flux:field>
                                </div>
                                <div class="md:col-span-4">
                                    <flux:field>
                                        <flux:label>الصدر (الشطر الأول)</flux:label>
                                        <flux:input type="text" wire:model="newSadr" placeholder="الصدر..." required />
                                        <flux:error name="newSadr" />
                                    </flux:field>
                                </div>
                                <div class="md:col-span-4">
                                    <flux:field>
                                        <flux:label>العجز (الشطر الثاني)</flux:label>
                                        <flux:input type="text" wire:model="newAjuz" placeholder="العجز..." required />
                                        <flux:error name="newAjuz" />
                                    </flux:field>
                                </div>
                                <div class="md:col-span-2">
                                    <flux:button type="submit" variant="primary" class="w-full" icon="plus">إضافة</flux:button>
                                </div>
                            </form>
                        </div>

                        {{-- Bulk Import Panel --}}
                        @if ($showBulkImport)
                            <div class="bg-primary/5 dark:bg-primary/10 p-5 rounded-xl border border-primary/20 space-y-4">
                                <div class="flex items-center justify-between">
                                    <flux:heading size="sm" class="font-bold text-primary dark:text-primary-400">استيراد أبيات المنظومة جماعياً</flux:heading>
                                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="closeBulkImport" />
                                </div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                                    أدخل الأبيات أدناه بحيث يكون كل بيت في سطر منفصل، وافصل بين الصدر والعجز باستخدام <strong class="text-primary">مفتاح Tab</strong> أو <strong class="text-primary">مسافتين متتاليتين</strong> أو <strong class="text-primary">الرمز #</strong>.<br>
                                    مثال:<br>
                                    <code class="bg-zinc-100 dark:bg-zinc-800 px-1 py-0.5 rounded">الصدر الأول # العجز الأول</code>
                                </p>
                                <flux:field>
                                    <flux:textarea rows="8" wire:model="bulkText" placeholder="أدخل الأبيات هنا..." />
                                    <flux:error name="bulkText" />
                                </flux:field>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="closeBulkImport">إلغاء</flux:button>
                                    <flux:button size="sm" variant="primary" wire:click="importBulkVerses">بدء الاستيراد</flux:button>
                                </div>
                            </div>
                        @endif

                        {{-- Verses List --}}
                        <div class="space-y-3">
                            <flux:heading size="sm" class="font-bold text-zinc-800 dark:text-zinc-200">أبيات المنظومة</flux:heading>
                            
                            <div class="border border-zinc-100 dark:border-zinc-800 rounded-xl overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse ($selectedOde->verses->sortBy('verse_number') as $verse)
                                    <div class="p-3 flex items-center gap-4 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10">
                                        @if ($editingVerseId === $verse->id)
                                            {{-- Inline Edit Form --}}
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                                                <div class="md:col-span-2">
                                                    <flux:input type="number" min="1" wire:model="editingVerseNumber" class="w-full" />
                                                    <flux:error name="editingVerseNumber" />
                                                </div>
                                                <div class="md:col-span-4">
                                                    <flux:input type="text" wire:model="editingSadr" class="w-full" />
                                                    <flux:error name="editingSadr" />
                                                </div>
                                                <div class="md:col-span-4">
                                                    <flux:input type="text" wire:model="editingAjuz" class="w-full" />
                                                    <flux:error name="editingAjuz" />
                                                </div>
                                                <div class="md:col-span-2 flex gap-1">
                                                    <flux:button size="sm" variant="primary" icon="check" wire:click="saveEditingVerse" title="حفظ" />
                                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="cancelEditVerse" title="إلغاء" />
                                                </div>
                                            </div>
                                        @else
                                            {{-- Normal Verse Row --}}
                                            <div class="w-10 text-center font-bold text-xs text-zinc-400 bg-zinc-50 dark:bg-zinc-800 rounded py-1">
                                                {{ $verse->verse_number }}
                                            </div>
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-4 text-center">
                                                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 border-l border-dashed border-zinc-200 dark:border-zinc-700/50 pr-2 text-right md:text-center">
                                                    {{ $verse->sadr }}
                                                </div>
                                                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200 pl-2 text-left md:text-center">
                                                    {{ $verse->ajuz }}
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startEditVerse({{ $verse->id }})" title="تعديل البيت" />
                                                <flux:button size="sm" variant="ghost" icon="trash" color="red" wire:click="deleteVerse({{ $verse->id }})" wire:confirm="هل أنت متأكد من حذف هذا البيت؟" title="حذف البيت" />
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="p-8 text-center">
                                        <flux:icon icon="list-bullet" class="size-8 mx-auto text-zinc-300 mb-2" />
                                        <flux:text class="text-zinc-400 text-sm">لا توجد أبيات مضافة لهذه المنظومة بعد</flux:text>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Create/Edit Ode Modal --}}
    <flux:modal name="ode-modal" class="md:w-[500px]">
        <form wire:submit.prevent="saveOde" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingOdeId ? 'تعديل بيانات المنظومة' : 'منظومة علمية جديدة' }}</flux:heading>
                <flux:subheading>أدخل بيانات المنظومة العامة ليتسنى لك لاحقاً إضافة الأبيات لها.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>اسم المنظومة</flux:label>
                    <flux:input wire:model="name" placeholder="مثال: تحفة الأطفال، الجزرية..." required />
                    <flux:error name="name" />
                </flux:field>
                
                <flux:field>
                    <flux:label>وصف المنظومة</flux:label>
                    <flux:textarea wire:model="description" placeholder="وصف موجز للمنظومة (مؤلفها، بحرها، إلخ)..." rows="4" />
                    <flux:error name="description" />
                </flux:field>

                @if (!$editingOdeId)
                    <flux:field>
                        <flux:label>أبيات المنظومة كاملة (اختياري)</flux:label>
                        <flux:textarea wire:model="versesText" placeholder="أدخل الأبيات هنا...&#10;مثال:&#10;الصدر الأول  العجز الأول&#10;الصدر الثاني  العجز الثاني&#10;(تأكد من ترك مسافتين أو Tab بين الصدر والعجز، وكل بيت في سطر جديد)" rows="8" />
                        <flux:error name="versesText" />
                    </flux:field>
                @endif
            </div>

            <div class="flex gap-2 justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">إلغاء</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">حفظ</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
