<x-layouts.auth.split title="في انتظار الموافقة">
    <div class="flex flex-col gap-8" dir="rtl">
        {{-- بطاقة التأكيد --}}
        <div class="flex flex-col items-center gap-4 text-center">
            <div class="bg-emerald-50 dark:bg-emerald-900/20 p-4 rounded-full">
                <flux:icon icon="check-circle" variant="solid" class="size-10 text-emerald-500" />
            </div>

            <div class="flex flex-col gap-2">
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">تم استلام طلب التسجيل</h1>
                <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                    تم إرسال طلبك بنجاح، سيتم مراجعة بياناتك من قِبل إدارة المجمع.
                    سيتم إشعارك عند قبول الطلب.
                </p>
            </div>

            <flux:button href="{{ route('home') }}" variant="primary" wire:navigate class="w-full !bg-maroon hover:!bg-burgundy">
                <flux:icon icon="home" class="size-4" />
                العودة إلى الصفحة الرئيسية
            </flux:button>
        </div>

        {{-- رحلة طلب التسجيل --}}
        <div class="pt-8 border-t border-zinc-100 dark:border-zinc-800">
            <h2 class="text-center font-bold text-zinc-800 dark:text-zinc-100 mb-6">رحلة طلب التسجيل</h2>

            <div class="flex items-start justify-between gap-1">
                @foreach([
                    ['icon' => 'user-plus', 'title' => 'إنشاء حساب', 'desc' => 'تعبئة البيانات', 'color' => 'text-amber-500 bg-amber-50 dark:bg-amber-900/20'],
                    ['icon' => 'magnifying-glass', 'title' => 'مراجعة الطلب', 'desc' => 'من قبل الإدارة', 'color' => 'text-blue-500 bg-blue-50 dark:bg-blue-900/20'],
                    ['icon' => 'check-circle', 'title' => 'قبول الطلب', 'desc' => 'أو رفضه', 'color' => 'text-emerald-500 bg-emerald-50 dark:bg-emerald-900/20'],
                    ['icon' => 'bell', 'title' => 'إشعار المستخدم', 'desc' => 'بنتيجة الطلب', 'color' => 'text-purple-500 bg-purple-50 dark:bg-purple-900/20'],
                ] as $i => $step)
                    <div class="flex flex-col items-center text-center flex-1">
                        <div class="size-11 rounded-full {{ $step['color'] }} flex items-center justify-center">
                            <flux:icon :icon="$step['icon']" variant="solid" class="size-5" />
                        </div>
                        <div class="text-xs font-bold text-zinc-800 dark:text-zinc-100 mt-2">{{ $step['title'] }}</div>
                        <div class="text-[10px] text-zinc-400 mt-0.5">{{ $step['desc'] }}</div>
                    </div>
                    @if($i < 3)
                        <flux:icon icon="arrow-left" class="size-4 text-zinc-300 dark:text-zinc-700 mt-5 shrink-0" />
                    @endif
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:button type="submit" variant="ghost" class="w-full font-bold">
                تسجيل الخروج
            </flux:button>
        </form>
    </div>
</x-layouts.auth.split>
