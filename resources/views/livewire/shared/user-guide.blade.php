<div class="space-y-6" dir="rtl" x-data="{ open: null }">
    <div class="flex items-center gap-3">
        <div class="p-2.5 rounded-xl bg-maroon/10 text-maroon dark:bg-white/10 dark:text-white">
            <flux:icon icon="question-mark-circle" />
        </div>
        <div>
            <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">دليل الاستخدام</flux:heading>
            <flux:subheading class="text-zinc-400">شرح كل صفحة في النظام وكيفية الوصول إليها</flux:subheading>
        </div>
    </div>

    @if($sections->isEmpty())
        <div class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-16 text-center">
            <p class="text-sm text-zinc-400">لا يوجد محتوى دليل متاح لحسابك حاليًا.</p>
        </div>
    @endif

    @foreach($sections as $index => $section)
        <div class="rounded-2xl border border-zinc-100 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
            <button type="button" @click="open = (open === {{ $index }} ? null : {{ $index }})"
                class="w-full flex items-center justify-between p-4 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                <span class="font-bold text-zinc-800 dark:text-zinc-100">{{ $section['heading'] }}</span>
                <flux:icon icon="chevron-down" class="size-4 text-zinc-400 transition-transform"
                    x-bind:class="open === {{ $index }} ? 'rotate-180' : ''" />
            </button>

            <div x-show="open === {{ $index }}" x-collapse x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 divide-zinc-100 dark:divide-zinc-800 border-t border-zinc-100 dark:border-zinc-800">
                    @foreach($section['pages'] as $page)
                        <div class="p-4 flex items-start gap-3">
                            <div class="p-2 rounded-lg bg-zinc-50 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400 shrink-0">
                                <flux:icon :icon="$page['icon']" class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    @if($page['linkable'])
                                        <a href="{{ $page['url'] }}" wire:navigate class="text-sm font-bold text-zinc-800 dark:text-zinc-100 hover:text-maroon dark:hover:text-white">
                                            {{ $page['title'] }}
                                        </a>
                                    @else
                                        <span class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $page['title'] }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 leading-relaxed">{{ $page['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</div>
