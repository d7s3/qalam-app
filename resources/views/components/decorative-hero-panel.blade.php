{{--
    The tall panel beside the sign-in form.

    It carries the same three things the portal's own doorway does, in the same
    order: the mark, the geometric ground, and a hadith on seeking knowledge
    with its source and grading. The loose line drawings that used to fill it —
    a lantern, a crescent, a mushaf on a rehl — are gone for the reason
    `brand-ornament` was written: a repeating geometric ground reads as ornament
    at any size, where a single clip-art figure only reads as clip art.
--}}
@props([
    'hadith' => null,
    'variant' => 'light',
])

@php
    $isDark = $variant === 'dark';
    $saying = $hadith ?? \App\Support\KnowledgeHadiths::random();
@endphp

<div {{ $attributes->merge([
    'class' => 'relative hidden lg:flex flex-col justify-between h-full p-10 overflow-hidden '
        .($isDark
            ? 'bg-gradient-to-b from-[#3f1a19] via-maroon to-[#5c231f]'
            : 'bg-gradient-to-b from-[#f7efe0] via-[#faf5ea] to-[#fdfaf3]'),
]) }}>
    <x-brand-ornament class="{{ $isDark ? 'text-gold opacity-[0.08]' : 'text-maroon opacity-[0.05]' }}" />

    {{-- الشعار --}}
    <a href="{{ route('home') }}" class="relative z-10 flex flex-col items-center text-center" wire:navigate>
        <img src="{{ asset(config('brand.logo')) }}" alt="{{ config('brand.name') }}" class="h-14 object-contain mb-2 drop-shadow-sm" />
        <span class="font-extrabold text-lg {{ $isDark ? 'text-white' : 'text-maroon' }}">{{ config('brand.name') }}</span>
        <span class="text-xs font-medium mt-0.5 {{ $isDark ? 'text-white/70' : 'text-neutral-grey' }}">{{ config('brand.tagline') }}</span>
    </a>

    {{-- الحديث --}}
    <div class="relative z-10 my-8 flex flex-1 items-center">
        <div class="w-full rounded-[1.75rem] border p-8 text-center backdrop-blur-sm
            {{ $isDark ? 'border-gold/25 bg-black/15' : 'border-maroon/15 bg-white/50' }}">

            <div class="flex items-center gap-3">
                <span class="h-px flex-1 {{ $isDark ? 'bg-gradient-to-l from-transparent to-gold/45' : 'bg-gradient-to-l from-transparent to-maroon/25' }}"></span>
                <flux:icon icon="sparkles" class="size-3.5 {{ $isDark ? 'text-gold' : 'text-maroon/50' }}" />
                <span class="h-px flex-1 {{ $isDark ? 'bg-gradient-to-r from-transparent to-gold/45' : 'bg-gradient-to-r from-transparent to-maroon/25' }}"></span>
            </div>

            <p class="mt-6 font-zain text-lg leading-[1.9] text-balance {{ $isDark ? 'text-amber-50' : 'text-maroon' }}">
                «{{ $saying['text'] }}»
            </p>

            <div class="mt-5 flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
                <span class="text-[11px] font-medium {{ $isDark ? 'text-white/55' : 'text-maroon/60' }}">
                    {{ $saying['source'] }}
                </span>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold
                    {{ $isDark ? 'bg-gold/15 text-gold' : 'bg-maroon/8 text-maroon/70' }}">
                    {{ $saying['grade'] }}
                </span>
            </div>
        </div>
    </div>
</div>
