@props(['showAuthActions' => true])

@php
    $home = route('home');
    $anchor = fn (string $id) => request()->routeIs('home') ? "#{$id}" : "{$home}#{$id}";

    $links = [
        ['label' => 'الرئيسية', 'href' => $home],
        ['label' => 'الأسئلة الشائعة', 'href' => $anchor('faq')],
        ['label' => 'تواصل معنا', 'href' => $anchor('contact')],
    ];
@endphp

<header class="sticky top-0 z-50 w-full bg-accent-dark border-b border-gold/20">
    <div class="max-w-6xl mx-auto px-5 h-16 flex items-center justify-between gap-4">
        <div class="flex items-center gap-2 shrink-0">
            <span class="hidden sm:flex items-center gap-1 text-xs font-bold text-white/70 px-2.5 py-1.5 rounded-full border border-white/15 cursor-default">
                <flux:icon icon="language" class="size-3.5" />
                العربية
            </span>
            <a href="{{ $anchor('contact') }}" wire:navigate class="flex items-center gap-1.5 text-xs font-bold text-accent-dark bg-gold hover:brightness-110 px-3.5 py-1.5 rounded-full transition-all">
                <flux:icon icon="lifebuoy" class="size-3.5" />
                مساعدة
            </a>
        </div>

        <nav class="hidden md:flex items-center gap-7 text-sm font-bold text-white/80">
            @foreach ($links as $link)
                <a href="{{ $link['href'] }}" wire:navigate
                   class="relative py-1 transition-colors hover:text-gold after:absolute after:inset-x-0 after:-bottom-0.5 after:h-px after:origin-right after:scale-x-0 after:bg-gold after:transition-transform hover:after:scale-x-100">
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <a href="{{ $home }}" wire:navigate class="flex items-center gap-3 shrink-0">
            <div class="text-end hidden sm:block">
                <div class="font-extrabold text-white text-sm leading-tight">{{ config('brand.name') }}</div>
                <div class="text-[10px] text-gold/80">{{ config('brand.tagline') }}</div>
            </div>
            <span class="grid size-11 place-items-center rounded-2xl bg-[#fdfaf3] ring-1 ring-gold/30 shadow-sm">
                <img src="{{ asset(config('brand.logo')) }}" alt="{{ config('brand.name') }}" class="h-8 w-auto object-contain" />
            </span>
        </a>
    </div>
</header>
