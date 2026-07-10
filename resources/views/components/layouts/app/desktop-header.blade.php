@php
    $roleLabels = [
        'manager' => 'مدير',
        'supervisor' => 'مشرف',
        'teacher' => 'معلم حلقة',
        'student' => 'طالب',
        'guardian' => 'ولي أمر',
    ];
    $activeGuard = collect(['manager', 'supervisor', 'teacher', 'student', 'guardian'])
        ->first(fn ($guard) => auth()->guard($guard)->check());
    $currentUser = $activeGuard ? auth()->guard($activeGuard)->user() : null;
    $showSearch = in_array($activeGuard, ['manager', 'supervisor', 'teacher', 'student'], true);
@endphp

<div class="flex w-full items-center gap-4 bg-white dark:bg-zinc-900 border-b border-zinc-100 dark:border-zinc-800 px-6 py-3">
    <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

    <div class="flex-1 flex justify-center px-6 min-w-0">
        @if($activeGuard === 'student')
            <div class="w-full max-w-xl">
                <livewire:student.header-search />
            </div>
        @elseif($showSearch)
            <livewire:shared.header-search />
        @endif
    </div>

    <div class="flex items-center gap-3">
        @if($activeGuard && \Illuminate\Support\Facades\Route::has("{$activeGuard}.guide"))
            <a href="{{ route("{$activeGuard}.guide") }}" wire:navigate title="دليل الاستخدام"
                class="p-2 rounded-full hover:bg-zinc-100 dark:hover:bg-zinc-800">
                <flux:icon icon="question-mark-circle" class="size-5 text-zinc-500 dark:text-zinc-300" />
            </a>
        @endif

        @if($activeGuard)
            <livewire:shared.notification-bell />
        @endif

        @if($currentUser)
            <flux:dropdown position="bottom" align="end">
                <button type="button" class="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                    <div class="text-right hidden xl:block">
                        <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $currentUser->name }}</div>
                        <div class="text-xs text-zinc-400">{{ $roleLabels[$activeGuard] ?? '' }}</div>
                    </div>
                    <flux:avatar :name="$currentUser->name" :initials="$currentUser->initials()" size="sm" />
                    <flux:icon icon="chevron-down" class="size-4 text-zinc-400" />
                </button>

                <flux:menu>
                    <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                        <flux:avatar :name="$currentUser->name" :initials="$currentUser->initials()" />
                        <div class="grid flex-1 text-start text-sm leading-tight">
                            <flux:heading class="truncate">{{ $currentUser->name }}</flux:heading>
                            <flux:text class="truncate">{{ $currentUser->email }}</flux:text>
                        </div>
                    </div>
                    <flux:menu.separator />
                    @if($activeGuard === 'student')
                        <flux:menu.item :href="route('student.settings')" icon="cog" wire:navigate>
                            {{ __('إعدادات الحساب') }}
                        </flux:menu.item>
                        <form method="POST" action="{{ route('student.logout') }}" class="w-full">
                    @else
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('إعدادات الحساب') }}
                        </flux:menu.item>
                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @endif
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer">
                            {{ __('تسجيل الخروج') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>
</div>
