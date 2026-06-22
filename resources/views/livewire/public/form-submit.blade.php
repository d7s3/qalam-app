<div>
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
                background-color: color-mix(in srgb, var(--theme-color) 6%, #fafafa) !important;
            }
            @media (prefers-color-scheme: dark) {
                body {
                    background-color: color-mix(in srgb, var(--theme-color) 6%, #0a0a0a) !important;
                }
            }
            .accent-btn {
                background-color: var(--theme-color) !important;
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
            @media (prefers-color-scheme: dark) {
                .accent-ring, select, input[type="date"], textarea {
                    border-color: color-mix(in srgb, var(--theme-color) 35%, #27272a) !important;
                }
            }
            .accent-ring:focus, .accent-ring:focus-within, select:focus, input[type="date"]:focus, textarea:focus {
                border-color: var(--theme-color) !important;
                --tw-ring-color: var(--theme-color) !important;
            }
            
            /* Theme borders and check box inputs */
            .theme-border {
                border-color: color-mix(in srgb, var(--theme-color) 35%, #d4d4d8) !important;
            }
            @media (prefers-color-scheme: dark) {
                .theme-border {
                    border-color: color-mix(in srgb, var(--theme-color) 35%, #27272a) !important;
                }
            }
            
            /* Dashed image border */
            .theme-dashed-border {
                border-color: color-mix(in srgb, var(--theme-color) 60%, #d4d4d8) !important;
                background-color: color-mix(in srgb, var(--theme-color) 2%, #fafafa) !important;
            }
            @media (prefers-color-scheme: dark) {
                .theme-dashed-border {
                    border-color: color-mix(in srgb, var(--theme-color) 60%, #27272a) !important;
                    background-color: color-mix(in srgb, var(--theme-color) 2%, #0a0a0a) !important;
                }
            }
            .theme-dashed-border:hover {
                background-color: color-mix(in srgb, var(--theme-color) 6%, #f4f4f5) !important;
            }
            @media (prefers-color-scheme: dark) {
                .theme-dashed-border:hover {
                    background-color: color-mix(in srgb, var(--theme-color) 6%, #09090b) !important;
                }
            }
        </style>
    @endpush
    
    <div class="w-full max-w-2xl space-y-6">
        <!-- Logo -->
        <div class="flex items-center justify-center gap-3">
            <img src="{{ asset('images/altag_logo.png') }}" alt="Logo" class="h-12 object-contain">
            <span class="font-bold text-lg text-zinc-700 dark:text-zinc-300">مجمع التاج القرآني</span>
        </div>

        @if($submitted)
            <!-- Thank You Screen -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 p-8 shadow-md text-center space-y-6">
                <div class="w-20 h-20 rounded-full flex items-center justify-center mx-auto text-white" style="background-color: var(--theme-color)">
                    <flux:icon name="check" class="size-10" />
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-zinc-900 dark:text-white">تم إرسال ردك بنجاح!</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">شكرًا لتعاونك وتعبئة هذا النموذج. لقد تم استلام إجاباتك بنجاح.</p>
                </div>
                @if($form->description)
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-950 rounded-lg text-sm text-zinc-600 dark:text-zinc-400 border border-zinc-150 dark:border-zinc-850">
                        {{ $form->description }}
                    </div>
                @endif
            </div>
        @else
            <!-- Main Form Card -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-200 dark:border-zinc-800 shadow-md overflow-hidden">
                <!-- Header Banner -->
                @if($form->header_image_path)
                    <div class="h-44 w-full bg-cover bg-center" style="background-image: url('{{ asset('storage/' . $form->header_image_path) }}')"></div>
                @else
                    <div class="h-4 w-full" style="background-color: var(--theme-color)"></div>
                @endif

                <div class="p-6 md:p-8 space-y-6">
                    <!-- Title & Description -->
                    <div class="space-y-2 border-b border-zinc-100 dark:border-zinc-800 pb-5">
                        <h1 class="text-2xl md:text-3xl font-extrabold text-zinc-900 dark:text-white">{{ $form->title }}</h1>
                        @if($form->description)
                            <p class="text-sm text-zinc-500 dark:text-zinc-400 whitespace-pre-line mt-2 leading-relaxed">
                                {{ $form->description }}
                            </p>
                        @endif
                    </div>

                    <!-- Submission Form -->
                    <form wire:submit="submit" class="space-y-6">
                        @foreach($form->fields as $field)
                            @php
                                $fieldId = $field['id'];
                                $label = $field['label'];
                                $required = $field['required'] ?? false;
                            @endphp

                            <div class="space-y-2">
                                <label class="block text-sm font-semibold text-zinc-800 dark:text-zinc-200 field-label">
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
                                    <flux:input type="date" wire:model="answers.{{ $fieldId }}" class="accent-ring w-full" />
                                    <flux:error name="answers.{{ $fieldId }}" />
                                @endif

                                <!-- Select Field -->
                                @if($field['type'] === 'select')
                                    <select wire:model="answers.{{ $fieldId }}" class="block w-full rounded-lg border border-zinc-200 dark:border-zinc-800 bg-transparent px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 focus:outline-hidden focus:ring-2 focus:ring-offset-2 accent-ring">
                                        <option value="">-- اختر خياراً --</option>
                                        @foreach($field['options'] ?? [] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    <flux:error name="answers.{{ $fieldId }}" />
                                @endif

                                <!-- Multiselect Field -->
                                @if($field['type'] === 'multiselect')
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-zinc-50 dark:bg-zinc-950 p-4 rounded-lg border border-zinc-200 dark:border-zinc-850 theme-border">
                                        @foreach($field['options'] ?? [] as $optVal)
                                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                                <input type="checkbox" wire:model="answers.{{ $fieldId }}" value="{{ $optVal }}" class="rounded border-zinc-300 dark:border-zinc-700 text-accent focus:ring-accent" />
                                                <span class="text-zinc-700 dark:text-zinc-300">{{ $optVal }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <flux:error name="answers.{{ $fieldId }}" />
                                @endif

                                <!-- Image Field -->
                                @if($field['type'] === 'image')
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-center border-2 border-dashed border-zinc-300 dark:border-zinc-700 rounded-lg p-6 bg-zinc-50 dark:bg-zinc-950 hover:bg-zinc-100 dark:hover:bg-zinc-900 transition-colors relative cursor-pointer theme-dashed-border">
                                            <input type="file" wire:model="temp_uploads.{{ $fieldId }}" class="absolute inset-0 opacity-0 cursor-pointer w-full h-full" />
                                            <div class="text-center space-y-2">
                                                <flux:icon name="photo" class="size-8 mx-auto text-zinc-400" />
                                                <span class="block text-xs font-semibold text-zinc-600 dark:text-zinc-400">انقر هنا أو اسحب الصورة لرفعها</span>
                                                <span class="block text-[10px] text-zinc-400">صيغ الصور فقط (اقصى حجم: 10 ميجا)</span>
                                            </div>
                                        </div>

                                        <!-- Upload Preview -->
                                        @if(isset($temp_uploads[$fieldId]) && $temp_uploads[$fieldId])
                                            <div class="flex items-center gap-3 p-3 bg-zinc-100 dark:bg-zinc-800 rounded-lg">
                                                <flux:icon name="check-circle" class="size-5 text-emerald-500 shrink-0" />
                                                <div class="min-w-0 flex-1">
                                                    <span class="block text-xs font-semibold text-zinc-800 dark:text-zinc-200 truncate">
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
                        <div class="pt-6 border-t border-zinc-100 dark:border-zinc-800">
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
