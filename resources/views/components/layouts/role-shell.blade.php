<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    @include('partials.head')
    <title>{{ $title ?? config('app.name') }}</title>
</head>

<body class="min-h-screen bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased">
    <!-- Offline Indicator -->
    <div x-data="{ online: navigator.onLine }" 
         @online.window="online = true" 
         @offline.window="online = false"
         x-show="!online" 
         x-transition.opacity.duration.500ms
         style="display: none;"
         class="fixed top-0 left-0 right-0 z-[100] bg-red-500 text-white text-center py-1.5 px-4 text-sm font-bold shadow-md flex items-center justify-center gap-2">
         <flux:icon icon="exclamation-triangle" class="size-4" />
         <span>أنت غير متصل بشبكة الإنترنت حالياً. يرجى التحقق من اتصالك.</span>
    </div>
    <flux:sidebar sticky collapsible="mobile"
        class="self-start border-e border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        {{ $sidebar ?? '' }}

        <flux:spacer />

        <flux:sidebar.nav>
            @if(auth('student')->check())
                <flux:sidebar.item icon="cog" :href="route('student.settings')" :current="request()->routeIs('student.settings')" wire:navigate>
                    {{ __('إعدادات الحساب') }}
                </flux:sidebar.item>
            @else
                <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                    {{ __('إعدادات الحساب') }}
                </flux:sidebar.item>
            @endif

            @if(auth('student')->check())
                <form method="POST" action="{{ route('student.logout') }}" class="w-full">
            @else
                <form method="POST" action="{{ route('logout') }}" class="w-full">
            @endif
                @csrf
                <flux:sidebar.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer">
                    {{ __('تسجيل الخروج') }}
                </flux:sidebar.item>
            </form>
        </flux:sidebar.nav>

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile Menu -->
    <flux:sidebar collapsible="mobile" sticky
        class="lg:hidden border-e border-zinc-100 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse
                class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
        </flux:sidebar.header>

        {{ $sidebar ?? '' }}

        <flux:spacer />

        <flux:sidebar.nav>
            @if(auth('student')->check())
                <flux:sidebar.item icon="cog" :href="route('student.settings')" :current="request()->routeIs('student.settings')" wire:navigate>
                    {{ __('الإعدادات') }}
                </flux:sidebar.item>
            @else
                <flux:sidebar.item icon="cog" :href="route('profile.edit')" :current="request()->routeIs('profile.edit')" wire:navigate>
                    {{ __('الإعدادات') }}
                </flux:sidebar.item>
            @endif
            @if(auth('student')->check())
                <form method="POST" action="{{ route('student.logout') }}" class="w-full">
            @else
                <form method="POST" action="{{ route('logout') }}" class="w-full">
            @endif
                @csrf
                <flux:sidebar.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer">
                    {{ __('تسجيل الخروج') }}
                </flux:sidebar.item>
            </form>
        </flux:sidebar.nav>
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
        <flux:spacer />
        <x-layouts.app.header-user-menu />
    </flux:header>

    <flux:main class="!p-1 !pb-32 md:!p-8 md:!pb-32 lg:!pb-8">
        {{ $slot }}
    </flux:main>

    @if(str_contains(request()->url(), '/teacher/'))
        <x-teacher-bottom-nav />
    @endif

    @fluxScripts
</body>

</html>