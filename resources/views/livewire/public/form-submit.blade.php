<div class="w-full max-w-2xl space-y-6">
    @push('meta')
        <!-- Open Graph Tags for WhatsApp & Social Media Sharing -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="{{ $form->title }}">
        <meta property="og:description" content="{{ $form->description ?? 'الرجاء تعبئة هذا النموذج المخصص.' }}">
        @if($form->header_image_path)
            <meta property="og:image" content="{{ asset('storage/' . $form->header_image_path) }}">
        @else
            <meta property="og:image" content="{{ asset('images/altag_logo.png') }}">
        @endif
        <meta property="og:site_name" content="مجمع التاج القرآني">

        <title>{{ $form->title }} - مجمع التاج القرآني</title>
        
        <script>
            document.documentElement.classList.remove('dark');
        </script>
    @endpush

    @push('styles')
        <style>
            :root {
                --theme-color: {{ $form->color }};
                --theme-color-hover: color-mix(in srgb, {{ $form->color }} 85%, black);
                --color-accent: var(--theme-color);
                --color-accent-content: white;
                --color-accent-foreground: white;
            }
            body {
                background-color: color-mix(in srgb, var(--theme-color) 6%, #fff) !important;
            }
            input {
                color: #000 !important;
            }
            .accent-btn {
                background-color: color-mix(in srgb, var(--theme-color) 81%, #000) !important;
                color: white !important;
            }
            .accent-btn:hover {
                background-color: var(--theme-color-hover) !important;
            }
            .accent-ring:focus {
                --tw-ring-color: var(--theme-color) !important;
            }
            .field-label {
                border-right: 3px solid var(--theme-color);
                padding-right: 0.75rem;
            }
            
            /* Inputs and control borders */
            .accent-ring, select, input[type="date"], textarea {
                border-color: color-mix(in srgb, var(--theme-color) 35%, #d4d4d8) !important;
            }
            .accent-ring:focus, .accent-ring:focus-within, select:focus, input[type="date"]:focus, textarea:focus {
                border-color: var(--theme-color) !important;
                --tw-ring-color: var(--theme-color) !important;
            }
            
            /* Theme borders and check box inputs */
            .theme-border {
                border-color: color-mix(in srgb, var(--theme-color) 35%, #d4d4d8) !important;
            }
            
            /* Dashed image border */
            .theme-dashed-border {
                border-color: color-mix(in srgb, var(--theme-color) 60%, #d4d4d8) !important;
                background-color: color-mix(in srgb, var(--theme-color) 2%, #fafafa) !important;
            }
            .theme-dashed-border:hover {
                background-color: color-mix(in srgb, var(--theme-color) 6%, #f4f4f5) !important;
            }
            [x-cloak] {
                display: none !important;
            }
            @keyframes slideUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            @keyframes scalePop {
                0% {
                    transform: scale(0.8);
                    opacity: 0;
                }
                50% {
                    transform: scale(1.1);
                }
                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }
            .animate-slide-up {
                animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }
            .animate-scale-pop {
                animation: scalePop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
            }
            .delay-150 {
                animation-delay: 150ms;
            }
            .delay-300 {
                animation-delay: 300ms;
            }
        </style>
    @endpush
    
    <div class="w-full max-w-2xl space-y-6" x-data="{ agreed: {{ !empty($form->policy_text) ? 'false' : 'true' }} }">
        <!-- Logo -->
        <div class="flex items-center justify-center gap-3">
            <img src="{{ asset('images/altag_logo.png') }}" alt="Logo" class="h-12 object-contain">
            <span class="font-bold text-lg text-zinc-700">مجمع التاج القرآني</span>
        </div>

        @if($submitted)
            <!-- Thank You Screen -->
            <div class="bg-white rounded-2xl border border-zinc-200 p-8 shadow-md text-center space-y-6 animate-slide-up">
                <div class="w-15 h-15 rounded-full flex items-center justify-center mx-auto text-white animate-scale-pop" style="background-color: #7cc463; opacity: 0;">
                    <flux:icon name="check" class="size-10" />
                </div>
                <div class="animate-slide-up delay-150" style="opacity: 0;">
                    <h2 class="text-2xl font-bold text-zinc-900">تم إرسال ردك بنجاح!</h2>
                    @if($form->success_text)
                        <p class="text-sm text-zinc-600 whitespace-pre-line mt-4 leading-relaxed bg-zinc-50 p-4 rounded-xl border border-zinc-150 text-right animate-slide-up delay-300" style="opacity: 0;">
                            {{ $form->success_text }}
                        </p>
                    @else
                        <p class="text-sm text-zinc-500 mt-2">شكرًا لتعاونك وتعبئة هذا النموذج. لقد تم استلام إجاباتك بنجاح.</p>
                    @endif
                </div>
                @if(!$form->success_text && $form->description)
                    <div class="p-4 bg-zinc-50 rounded-lg text-sm text-zinc-600 border border-zinc-150 animate-slide-up delay-300" style="opacity: 0;">
                        {{ $form->description }}
                    </div>
                @endif
            </div>
        @else
            @if($form->policy_text)
                <!-- Policy Agreement Card -->
                <div x-show="!agreed" class="bg-white rounded-2xl border border-zinc-200 shadow-md overflow-hidden text-right">
                    @if($form->header_image_path)
                        <div class="h-44 w-full bg-cover bg-center" style="background-image: url('{{ asset('storage/' . $form->header_image_path) }}')"></div>
                    @else
                        <div class="h-4 w-full" style="background-color: var(--theme-color)"></div>
                    @endif

                    <div class="p-6 md:p-8 space-y-6">
                        <div class="space-y-2 border-b border-zinc-100 pb-5">
                            <h1 class="text-2xl md:text-3xl font-extrabold text-zinc-900">{{ $form->title }}</h1>
                            <p class="text-xs text-zinc-400 mt-1 font-bold">شروط وسياسة الاستخدام</p>
                        </div>

                        <div class="p-5 bg-zinc-50 rounded-xl text-zinc-700 text-sm whitespace-pre-line leading-relaxed border border-zinc-150 max-h-[350px] overflow-y-auto font-medium">
                            {{ $form->policy_text }}
                        </div>

                        <div class="pt-6 border-t border-zinc-100">
                            <flux:button type="button" @click="agreed = true" class="w-full accent-btn text-center justify-center font-bold text-base py-3">
                                موافق، الانتقال إلى الاستمارة
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Main Form Card -->
            <div x-show="agreed" class="bg-white rounded-2xl border border-zinc-200 shadow-md overflow-hidden" x-cloak>
                <!-- Header Banner -->
                @if($form->header_image_path)
                    <div class="h-44 w-full bg-cover bg-center" style="background-image: url('{{ asset('storage/' . $form->header_image_path) }}')"></div>
                @else
                    <div class="h-4 w-full" style="background-color: var(--theme-color)"></div>
                @endif

                <div class="p-6 md:p-8 space-y-6">
                    <!-- Title & Description -->
                    <div class="space-y-2 border-b border-zinc-100 pb-5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-zinc-900">{{ $form->title }}</h1>
                        @if($form->description)
                            <p class="text-sm text-zinc-500 whitespace-pre-line mt-2 leading-relaxed">
                                {{ $form->description }}
                            </p>
                        @endif
                    </div>

                    <!-- Submission Form -->
                    <form wire:submit="submit" class="space-y-10">
                        @foreach($form->fields as $field)
                            @php
                                $fieldId = $field['id'];
                                $label = $field['label'];
                                $required = $field['required'] ?? false;
                            @endphp

                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-zinc-800 field-label">
                                    {{ $label }}
                                    @if($required)
                                        <span class="text-rose-500">*</span>
                                    @endif
                                </label>

                                <!-- Text Field -->
                                @if($field['type'] === 'text')
                                    <flux:input wire:model="answers.{{ $fieldId }}" placeholder="اكتب إجابتك هنا..." class="accent-ring" />
                                    <flux:error name="answers.{{ $fieldId }}" />
                                @endif

                                <!-- Date Field -->
                                @if($field['type'] === 'date')
                                    <div class="grid grid-cols-3 gap-2">
                                        <!-- Day -->
                                        <div>
                                            <select wire:model="date_parts.{{ $fieldId }}.day" class="block w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm text-zinc-800 focus:outline-hidden focus:ring-2 focus:ring-offset-2 accent-ring">
                                                <option value="">اليوم</option>
                                                @foreach(range(1, 31) as $d)
                                                    <option value="{{ $d }}">{{ $d }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <!-- Month -->
                                        <div>
                                            <select wire:model="date_parts.{{ $fieldId }}.month" class="block w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm text-zinc-800 focus:outline-hidden focus:ring-2 focus:ring-offset-2 accent-ring">
                                                <option value="">الشهر</option>
                                                @foreach(range(1, 12) as $m)
                                                    <option value="{{ $m }}">{{ $m }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <!-- Year -->
                                        <div>
                                            <select wire:model="date_parts.{{ $fieldId }}.year" class="block w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm text-zinc-800 focus:outline-hidden focus:ring-2 focus:ring-offset-2 accent-ring">
                                                <option value="">السنة</option>
                                                @foreach(range(now()->year, now()->year - 80) as $y)
                                                    <option value="{{ $y }}">{{ $y }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <flux:error name="answers.{{ $fieldId }}" />
                                @endif

                                <!-- Select Field -->
                                @if($field['type'] === 'select')
                                    <select wire:model="answers.{{ $fieldId }}" class="block w-full rounded-lg border border-zinc-200 bg-transparent px-3 py-2 text-sm text-zinc-800 focus:outline-hidden focus:ring-2 focus:ring-offset-2 accent-ring">
                                        <option value="">-- اختر خياراً --</option>
                                        @foreach($field['options'] ?? [] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                        @if($field['allow_other'] ?? false)
                                            <option value="أخرى">أخرى</option>
                                        @endif
                                    </select>
                                    <flux:error name="answers.{{ $fieldId }}" />

                                    @if(($field['allow_other'] ?? false) && ($answers[$fieldId] ?? '') === 'أخرى')
                                        <div class="mt-2 animate-in fade-in slide-in-from-top-1 duration-250">
                                            <flux:input wire:model="other_answers.{{ $fieldId }}" placeholder="يرجى كتابة الخيار الآخر هنا..." class="accent-ring" />
                                            <flux:error name="other_answers.{{ $fieldId }}" />
                                        </div>
                                    @endif
                                @endif

                                <!-- Multiselect Field -->
                                @if($field['type'] === 'multiselect')
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-zinc-50 p-4 rounded-lg border border-zinc-200 theme-border">
                                        @foreach($field['options'] ?? [] as $optVal)
                                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                                <input type="checkbox" wire:model="answers.{{ $fieldId }}" value="{{ $optVal }}" class="rounded border-zinc-300 text-accent focus:ring-accent" />
                                                <span class="text-zinc-700">{{ $optVal }}</span>
                                            </label>
                                        @endforeach
                                        @if($field['allow_other'] ?? false)
                                            <label class="flex items-center gap-2 cursor-pointer text-sm font-semibold">
                                                <input type="checkbox" wire:model="answers.{{ $fieldId }}" value="أخرى" class="rounded border-zinc-300 text-accent focus:ring-accent" />
                                                <span class="text-zinc-800">أخرى</span>
                                            </label>
                                        @endif
                                    </div>
                                    <flux:error name="answers.{{ $fieldId }}" />

                                    @if(($field['allow_other'] ?? false) && in_array('أخرى', $answers[$fieldId] ?? []))
                                        <div class="mt-2 animate-in fade-in slide-in-from-top-1 duration-250">
                                            <flux:input wire:model="other_answers.{{ $fieldId }}" placeholder="يرجى كتابة الخيار الآخر هنا..." class="accent-ring" />
                                            <flux:error name="other_answers.{{ $fieldId }}" />
                                        </div>
                                    @endif
                                @endif

                                <!-- Image Field -->
                                @if($field['type'] === 'image')
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-center border-2 border-dashed border-zinc-300 rounded-lg p-6 bg-zinc-50 hover:bg-zinc-100 transition-colors relative cursor-pointer theme-dashed-border">
                                            <input type="file" wire:model="temp_uploads.{{ $fieldId }}" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                                            <div class="text-center space-y-2">
                                                <flux:icon name="photo" class="size-8 mx-auto text-zinc-400" />
                                                <span class="block text-xs font-semibold text-zinc-600">انقر هنا أو اسحب الصورة لرفعها</span>
                                                <span class="block text-[10px] text-zinc-400">صيغ الصور فقط (اقصى حجم: 10 ميجا)</span>
                                            </div>
                                        </div>

                                        <!-- Upload Preview -->
                                        @if(isset($temp_uploads[$fieldId]) && $temp_uploads[$fieldId])
                                            <div class="flex items-center gap-3 p-3 bg-zinc-100 rounded-lg">
                                                <flux:icon name="check-circle" class="size-5 text-emerald-500 shrink-0" />
                                                <div class="min-w-0 flex-1">
                                                    <span class="block text-xs font-semibold text-zinc-800 truncate">
                                                        {{ $temp_uploads[$fieldId]->getClientOriginalName() }}
                                                    </span>
                                                    <span class="block text-[10px] text-zinc-400">
                                                        جاهز للرفع والتخفيف
                                                    </span>
                                                </div>
                                            </div>
                                        @endif
                                        <flux:error name="temp_uploads.{{ $fieldId }}" />
                                    </div>
                                @endif
                            </div>
                        @endforeach

                        <!-- Submit Button -->
                        <div class="pt-6 border-t border-zinc-100">
                            <flux:button type="submit" class="w-full accent-btn text-center justify-center font-bold text-base py-3" wire:loading.attr="disabled">
                                <span wire:loading.remove>تقديم الرد والبيانات</span>
                                <span wire:loading>جاري معالجة ورفع الرد...</span>
                            </flux:button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

</div>
