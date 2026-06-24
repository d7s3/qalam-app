<div class="space-y-6" x-data="{ color: '{{ $color }}' }">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">
                {{ $isEditing ? 'تعديل تصميم النموذج' : 'إنشاء نموذج واستمارة جديدة' }}
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">صمم الحقول والخيارات المخصصة للاستمارة وحدد حقول الربط مع حسابات الطلاب.</p>
        </div>
        <div class="flex gap-3">
            <flux:button as="a" :href="route('supervisor.forms')" variant="ghost">إلغاء</flux:button>
            <flux:button wire:click="save" variant="primary" class="bg-accent hover:bg-accent/90 text-white border-0">
                حفظ النموذج
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Settings (Left Column) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-5 space-y-4">
                <h2 class="text-lg font-bold text-zinc-900 dark:text-white">خصائص النموذج</h2>
                
                <!-- Title -->
                <flux:field>
                    <flux:label>عنوان النموذج *</flux:label>
                    <flux:input wire:model="title" placeholder="مثال: استمارة تسجيل الطلاب الجدد" />
                    <flux:error name="title" />
                </flux:field>

                <!-- Description -->
                <flux:field>
                    <flux:label>وصف النموذج</flux:label>
                    <flux:textarea wire:model="description" rows="3" placeholder="اكتب وصفاً أو إرشادات للمتقدمين لتعبئة الاستمارة..." />
                    <flux:error name="description" />
                </flux:field>

                <!-- Policy Text -->
                <flux:field>
                    <flux:label>سياسة الاستخدام والشروط (اختياري)</flux:label>
                    <flux:textarea wire:model="policy_text" rows="4" placeholder="اكتب شروط الاستخدام أو السياسة التي يجب على المتقدم الموافقة عليها للدخول..." />
                    <flux:error name="policy_text" />
                </flux:field>

                <!-- Success Text -->
                <flux:field>
                    <flux:label>رسالة إتمام الاستمارة المخصصة (اختياري)</flux:label>
                    <flux:textarea wire:model="success_text" rows="3" placeholder="اكتب رسالة تظهر للمتقدم بعد إرسال استمارته بنجاح..." />
                    <flux:error name="success_text" />
                </flux:field>

                <!-- Slug -->
                <flux:field>
                    <flux:label>رابط الاستمارة المخصص *</flux:label>
                    <div class="flex items-stretch rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-800">
                        <span class="bg-zinc-50 dark:bg-zinc-800 text-zinc-500 px-3 flex items-center text-xs dir-ltr select-none border-e border-zinc-200 dark:border-zinc-800">
                            /f/
                        </span>
                        <input wire:model="slug" type="text" class="flex-1 px-3 py-1.5 text-sm bg-transparent border-0 focus:ring-0 dir-ltr text-left outline-hidden" placeholder="registration-2026" />
                    </div>
                    <flux:error name="slug" />
                </flux:field>

                <!-- Color -->
                <flux:field>
                    <flux:label>اللون المميز (سمة النموذج) *</flux:label>
                    <div class="flex items-center gap-3">
                        <input type="color" wire:model="color" x-model="color" class="w-10 h-10 rounded-lg cursor-pointer border-0 p-0" />
                        <flux:input wire:model="color" x-model="color" placeholder="#7a2727" class="flex-1" />
                    </div>
                    <flux:error name="color" />
                </flux:field>

                <!-- Header Image -->
                <flux:field>
                    <flux:label>الصورة الرئيسية (رأس الصفحة)</flux:label>
                    <div class="space-y-3">
                        @if($header_image_path)
                            <div class="relative rounded-lg overflow-hidden border border-zinc-200 dark:border-zinc-800 h-24">
                                <img src="{{ asset('storage/' . $header_image_path) }}" class="w-full h-full object-cover" />
                                <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                                    <span class="text-xs text-white">صورة رأس الصفحة الحالية</span>
                                </div>
                            </div>
                        @endif

                        <input type="file" wire:model="header_image_file" class="block w-full text-xs text-zinc-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 dark:file:bg-zinc-800 dark:file:text-zinc-300 file:cursor-pointer" />
                        <flux:error name="header_image_file" />
                    </div>
                </flux:field>
            </div>
        </div>

        <!-- Field Designer (Right Columns) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-5 space-y-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">حقول النموذج واستمارة الإدخال</h2>
                    
                    <!-- Add Field Button Dropdown -->
                    <div class="flex gap-2">
                        <flux:button wire:click="addField('text', 'حقل نص قصير')" size="sm" icon="plus" class="text-xs">
                            نص قصير
                        </flux:button>
                        <flux:button wire:click="addField('image', 'تحميل صورة')" size="sm" icon="plus" class="text-xs">
                            صورة
                        </flux:button>
                        <flux:button wire:click="addField('select', 'حقل خيارات')" size="sm" icon="plus" class="text-xs">
                            خيارات
                        </flux:button>
                        <flux:button wire:click="addField('multiselect', 'حقل خيارات متعددة')" size="sm" icon="plus" class="text-xs">
                            خيارات متعددة
                        </flux:button>
                        <flux:button wire:click="addField('date', 'حقل تاريخ')" size="sm" icon="plus" class="text-xs">
                            تاريخ
                        </flux:button>
                    </div>
                </div>

                <flux:error name="fields" />

                <!-- Fields List -->
                <div class="space-y-4">
                    @foreach($fields as $index => $field)
                        <div class="p-4 bg-zinc-50 dark:bg-zinc-950 rounded-lg border border-zinc-200 dark:border-zinc-800 space-y-4">
                            <!-- Field Header Info -->
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-zinc-200 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300">
                                        @switch($field['type'])
                                            @case('text') نص قصير @break
                                            @case('image') صورة @break
                                            @case('select') قائمة خيارات @break
                                            @case('multiselect') خيارات متعددة @break
                                            @case('date') تاريخ @break
                                        @endswitch
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <flux:button wire:click="moveUp({{ $index }})" size="sm" variant="ghost" icon="chevron-up" class="h-8 w-8" :disabled="$index === 0" />
                                    <flux:button wire:click="moveDown({{ $index }})" size="sm" variant="ghost" icon="chevron-down" class="h-8 w-8" :disabled="$index === count($fields) - 1" />
                                    <flux:button wire:click="removeField({{ $index }})" size="sm" variant="ghost" icon="trash" class="h-8 w-8 text-rose-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/20" />
                                </div>
                            </div>

                            <!-- Inputs Row -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Label -->
                                <div class="md:col-span-2">
                                    <flux:field>
                                        <flux:label>اسم الحقل (السؤال) *</flux:label>
                                        <flux:input wire:model="fields.{{ $index }}.label" placeholder="مثال: الاسم الكامل للطالب" />
                                        <flux:error name="fields.{{ $index }}.label" />
                                    </flux:field>
                                </div>

                                <!-- Required -->
                                <div class="flex items-center md:pt-6">
                                    <flux:checkbox wire:model="fields.{{ $index }}.required" label="حقل إلزامي التعبئة" />
                                </div>
                            </div>

                            <!-- Field Designations (Only for text type fields) -->
                            @if($field['type'] === 'text')
                                <div class="flex flex-wrap gap-4 p-3 bg-white dark:bg-zinc-900 rounded-md border border-zinc-150 dark:border-zinc-800 text-xs">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox"
                                            wire:click="toggleNameDesignation({{ $index }})"
                                            @checked($field['is_student_name'] ?? false)
                                            class="rounded border-zinc-300 text-accent focus:ring-accent" />
                                        <span class="text-zinc-700 dark:text-zinc-300 font-medium">اعتماد هذا الحقل كـ (اسم الطالب) عند إنشاء الحساب</span>
                                    </label>

                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="checkbox"
                                            wire:click="toggleUsernameDesignation({{ $index }})"
                                            @checked($field['is_student_username'] ?? false)
                                            class="rounded border-zinc-300 text-accent focus:ring-accent" />
                                        <span class="text-zinc-700 dark:text-zinc-300 font-medium">اعتماد هذا الحقل كـ (اسم مستخدم الطالب) للولوج للنظام</span>
                                    </label>
                                </div>
                            @endif

                            <!-- Choices/Options configuration (Only for select and multiselect) -->
                            @if(in_array($field['type'], ['select', 'multiselect']))
                                <div class="space-y-2 border-t border-zinc-200 dark:border-zinc-800 pt-3">
                                    <flux:label>خيارات الإدخال للنموذج *</flux:label>
                                    <div class="space-y-2">
                                        @foreach($field['options'] ?? [] as $optIndex => $option)
                                            <div class="flex items-center gap-2">
                                                <flux:input wire:model="fields.{{ $index }}.options.{{ $optIndex }}" placeholder="أدخل اسم الخيار..." class="flex-1" />
                                                <flux:button wire:click="removeOption({{ $index }}, {{ $optIndex }})" size="sm" variant="ghost" icon="trash" class="text-rose-500" />
                                            </div>
                                        @endforeach
                                    </div>
                                    <flux:button wire:click="addOption({{ $index }})" size="sm" variant="ghost" icon="plus" class="text-xs">
                                        إضافة خيار جديد
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
