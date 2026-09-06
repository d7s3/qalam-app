@props([
    'sidebar' => false,
])

@php
    // Name and mark both come from the brand config, so handing the app to
    // another organisation is a change to .env rather than to this file.
    $brandName = config('brand.name');
    $brandLogo = asset(config('brand.logo'));
@endphp

@if($sidebar)
    <flux:sidebar.brand name="{{ $brandName }}" class="text-maroon dark:text-white" {{ $attributes->merge(['href' => route('home')]) }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-8 object-contain" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand name="{{ $brandName }}" class="text-maroon dark:text-white" {{ $attributes->merge(['href' => route('home')]) }}>
        <x-slot name="logo" class="flex items-center justify-center">
            <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="h-8 object-contain" />
        </x-slot>
    </flux:brand>
@endif
