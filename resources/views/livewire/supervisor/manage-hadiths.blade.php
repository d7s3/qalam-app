<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 rounded-lg bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon icon="document-text" />
            </div>
            <div>
                <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">إدارة الأحاديث الشريفة</flux:heading>
                <flux:subheading>إضافة وتعديل الأحاديث النبوية، أسانيدها، متونها، والأحكام الخاصة بها</flux:subheading>
            </div>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="createHadith">حديث جديد</flux:button>
    </div>

    {{-- Hadith Text Select Bar --}}
    <flux:card class="p-4 bg-zinc-50/50 dark:bg-zinc-800/30 border border-zinc-100 dark:border-zinc-800">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <flux:label class="whitespace-nowrap font-bold">متن الأحاديث المختار:</flux:label>
                <flux:select wire:model.live="selectedTextId" placeholder="اختر المتن..." class="w-64">
                    @foreach ($texts as $text)
                        <flux:select.option value="{{ $text->id }}">{{ $text->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($selectedTextId)
                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editSelectedText" title="تعديل المتن المختار" />
                    <flux:button size="sm" variant="ghost" icon="trash" color="red" wire:click="deleteSelectedText" wire:confirm="هل أنت متأكد من حذف هذا المتن بالكامل؟ سيتم مسح جميع الفصول والأحاديث التابعة له." title="حذف المتن المختار" />
                @endif
            </div>
            <flux:button size="sm" variant="filled" icon="plus" wire:click="createText">متن حديث جديد</flux:button>
        </div>
    </flux:card>

    {{-- Main Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {{-- Hadiths List Sidebar --}}
        <div class="{{ $selectedHadithId ? 'lg:col-span-4' : 'lg:col-span-12' }} space-y-4">
            <div class="flex flex-col sm:flex-row gap-2">
                <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search" placeholder="بحث عن حديث، باب، أو حكم..." class="flex-1" />
                <flux:select wire:model.live="filterChapterId" placeholder="تصفية بالباب..." class="sm:w-48">
                    <flux:select.option value="">كل الأبواب</flux:select.option>
                    @foreach ($chapters as $chapter)
                        <flux:select.option value="{{ $chapter->id }}">{{ $chapter->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/50 flex items-center justify-between">
                    <span class="font-bold text-sm text-zinc-700 dark:text-zinc-300">الأحاديث المسجلة ({{ $hadithsList->count() }})</span>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-[600px] overflow-y-auto">
                    @forelse ($hadithsList as $hadith)
                        <div class="p-4 flex items-start justify-between gap-4 transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/20 {{ $selectedHadithId === $hadith->id ? 'bg-zinc-50 dark:bg-zinc-800/40 border-r-4 border-primary' : '' }}">
                            <div class="flex-1 min-w-0 cursor-pointer" wire:click="selectHadith({{ $hadith->id }})">
                                <flux:heading size="sm" class="font-bold truncate text-zinc-900 dark:text-white">{{ $hadith->name }}</flux:heading>
                                @if ($hadith->chapter)
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">الباب: {{ $hadith->chapter->name }}</p>
                                @endif
                                <div class="flex items-center gap-2 mt-2">
                                    <flux:badge size="sm" variant="neutral">{{ $hadith->lines_count ?? $hadith->lines()->count() }} سطر</flux:badge>
                                    @if ($hadith->ruling)
                                        <flux:badge size="sm" color="indigo" variant="outline">{{ $hadith->ruling }}</flux:badge>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editHadith({{ $hadith->id }})" title="تعديل الحديث" />
                                <flux:button size="sm" variant="ghost" icon="trash" color="red" wire:click="deleteHadith({{ $hadith->id }})" wire:confirm="هل أنت متأكد من حذف هذا الحديث وجميع أسطر المتن التابعة له؟" title="حذف الحديث" />
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <flux:icon icon="document-text" class="size-8 mx-auto text-zinc-400 mb-2" />
                            <flux:text class="text-zinc-400 text-sm">لا توجد أحاديث مسجلة حالياً</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Lines Management for Selected Hadith --}}
        @if ($selectedHadithId)
            <div class="lg:col-span-8 space-y-6">
                @if ($selectedHadith)
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-xs p-6 space-y-6">
                        {{-- Hadith Info Header --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:heading size="lg" class="font-bold text-zinc-900 dark:text-white">{{ $selectedHadith->name }}</flux:heading>
                                    <flux:badge color="zinc">{{ $selectedHadith->lines->count() }} سطر</flux:badge>
                                    @if ($selectedHadith->ruling)
                                        <flux:badge color="indigo">{{ $selectedHadith->ruling }}</flux:badge>
                                    @endif
                                </div>
                                @if ($selectedHadith->chapter)
                                    <p class="text-sm text-zinc-600 dark:text-zinc-400 mt-1"><strong>الباب:</strong> {{ $selectedHadith->chapter->name }}</p>
                                @endif
                                @if ($selectedHadith->sanad)
                                    <p class="text-xs text-zinc-500 dark:text-zinc-500 mt-1"><strong>السند:</strong> {{ $selectedHadith->sanad }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="filled" icon="arrow-up-on-square-stack" wire:click="openBulkImport">
                                    استيراد جماعي للمتن
                                </flux:button>
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="selectHadith(null)">
                                    إغلاق المعاينة
                                </flux:button>
                            </div>
                        </div>

                        {{-- Add Single Line Form --}}
                        <div class="bg-zinc-50/50 dark:bg-zinc-800/20 p-4 rounded-xl border border-zinc-100 dark:border-zinc-800 space-y-4">
                            <flux:heading size="sm" class="font-bold text-zinc-800 dark:text-zinc-200">إضافة سطر متن جديد</flux:heading>
                            
                            <form wire:submit.prevent="saveLine" class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                <div class="md:col-span-2">
                                    <flux:field>
                                        <flux:label>رقم السطر</flux:label>
                                        <flux:input type="number" min="1" wire:model="newLineNumber" required />
                                        <flux:error name="newLineNumber" />
                                    </flux:field>
                                </div>
                                <div class="md:col-span-8">
                                    <flux:field>
                                        <flux:label>نص السطر</flux:label>
                                        <flux:input type="text" wire:model="newLineText" placeholder="اكتب نص سطر الحديث هنا..." required />
                                        <flux:error name="newLineText" />
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
                                    <flux:heading size="sm" class="font-bold text-primary dark:text-primary-400">استيراد متن الحديث جماعياً</flux:heading>
                                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="closeBulkImport" />
                                </div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 leading-relaxed">
                                    أدخل متن الحديث أدناه بحيث يتم تقسيم الأسطر تلقائياً بناءً على السطر الجديد (كل سطر نصي يمثل سطر متن مستقل في الحديث).
                                </p>
                                <flux:field>
                                    <flux:textarea rows="8" wire:model="bulkText" placeholder="أدخل أسطر متن الحديث هنا..." />
                                    <flux:error name="bulkText" />
                                </flux:field>
                                <div class="flex justify-end gap-2">
                                    <flux:button size="sm" variant="ghost" wire:click="closeBulkImport">إلغاء</flux:button>
                                    <flux:button size="sm" variant="primary" wire:click="importBulkLines">بدء الاستيراد</flux:button>
                                </div>
                            </div>
                        @endif

                        {{-- Lines List --}}
                        <div class="space-y-3">
                            <flux:heading size="sm" class="font-bold text-zinc-800 dark:text-zinc-200">نص متن الحديث (الأسطر)</flux:heading>
                            
                            <div class="border border-zinc-100 dark:border-zinc-800 rounded-xl overflow-hidden divide-y divide-zinc-100 dark:divide-zinc-800">
                                @forelse ($selectedHadith->lines->sortBy('line_number') as $line)
                                    <div class="p-3 flex items-center gap-4 hover:bg-zinc-50/50 dark:hover:bg-zinc-800/10">
                                        @if ($editingLineId === $line->id)
                                            {{-- Inline Edit Form --}}
                                            <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                                                <div class="md:col-span-2">
                                                    <flux:input type="number" min="1" wire:model="editingLineNumber" class="w-full" />
                                                    <flux:error name="editingLineNumber" />
                                                </div>
                                                <div class="md:col-span-8">
                                                    <flux:input type="text" wire:model="editingLineText" class="w-full" />
                                                    <flux:error name="editingLineText" />
                                                </div>
                                                <div class="md:col-span-2 flex gap-1">
                                                    <flux:button size="sm" variant="primary" icon="check" wire:click="saveEditingLine" title="حفظ" />
                                                    <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="cancelEditLine" title="إلغاء" />
                                                </div>
                                            </div>
                                        @else
                                            {{-- Normal Line Row --}}
                                            <div class="w-10 text-center font-bold text-xs text-zinc-400 bg-zinc-50 dark:bg-zinc-800 rounded py-1">
                                                {{ $line->line_number }}
                                            </div>
                                            <div class="flex-1 text-sm font-medium text-zinc-800 dark:text-zinc-200 text-right pr-2">
                                                {{ $line->text }}
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="startEditLine({{ $line->id }})" title="تعديل السطر" />
                                                <flux:button size="sm" variant="ghost" icon="trash" color="red" wire:click="deleteLine({{ $line->id }})" wire:confirm="هل أنت متأكد من حذف هذا السطر؟" title="حذف السطر" />
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="p-8 text-center">
                                        <flux:icon icon="list-bullet" class="size-8 mx-auto text-zinc-300 mb-2" />
                                        <flux:text class="text-zinc-400 text-sm">لا توجد أسطر متن مضافة لهذا الحديث بعد</flux:text>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Create/Edit Hadith Modal --}}
    <flux:modal name="hadith-modal" class="md:w-[600px]">
        <form wire:submit.prevent="saveHadith" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingHadithId ? 'تعديل بيانات الحديث' : 'حديث نبوي جديد' }}</flux:heading>
                <flux:subheading>أدخل بيانات الحديث العامة ليتسنى لك لاحقاً معاينة وإضافة أسطر المتن.</flux:subheading>
            </div>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>اسم الحديث</flux:label>
                        <flux:input wire:model="name" placeholder="مثال: إنما الأعمال بالنيات..." required />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>باب الحديث (اختر باباً مضافاً سابقاً)</flux:label>
                        <flux:select wire:model="hadithChapterId" placeholder="اختر الباب...">
                            <flux:select.option value="">-- بدون باب --</flux:select.option>
                            @foreach ($chapters as $chapter)
                                <flux:select.option value="{{ $chapter->id }}">{{ $chapter->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="hadithChapterId" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>أو أضف باباً جديداً</flux:label>
                        <flux:input wire:model="newChapterName" placeholder="مثال: باب بدء الوحي..." />
                        <flux:error name="newChapterName" />
                    </flux:field>

                    <flux:field>
                        <flux:label>حكم الحديث</flux:label>
                        <flux:input wire:model="ruling" placeholder="مثال: صحيح البخاري، متفق عليه..." />
                        <flux:error name="ruling" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>السند (الراوي)</flux:label>
                    <flux:input wire:model="sanad" placeholder="مثال: عن أمير المؤمنين أبي حفص..." />
                    <flux:error name="sanad" />
                </flux:field>

                @if (!$editingHadithId)
                    <flux:field>
                        <flux:label>متن الحديث كامل (اختياري)</flux:label>
                        <flux:textarea wire:model="linesText" placeholder="أدخل أسطر متن الحديث هنا... كل سطر يمثل سطر متن مستقل في الحديث" rows="8" />
                        <flux:error name="linesText" />
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

    {{-- Create/Edit HadithText Modal --}}
    <flux:modal name="text-modal" class="md:w-[500px]">
        <form wire:submit.prevent="saveText" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingTextId ? 'تعديل بيانات المتن' : 'متن أحاديث جديد' }}</flux:heading>
                <flux:subheading>أدخل بيانات المتن (مثال: الأربعين النووية) لتصنيف فصول وأحاديث هذا المتن داخله.</flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>اسم المتن</flux:label>
                    <flux:input wire:model="newTextName" placeholder="مثال: الأربعين النووية..." required />
                    <flux:error name="newTextName" />
                </flux:field>

                <flux:field>
                    <flux:label>الوصف</flux:label>
                    <flux:textarea wire:model="newTextDescription" placeholder="مثال: أربعون حديثاً نبوياً جمعها الإمام النووي..." rows="4" />
                    <flux:error name="newTextDescription" />
                </flux:field>
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
