@php
    $currentUser = auth('student')->check() ? auth('student')->user() : auth()->user();
@endphp
<flux:dropdown position="bottom" align="start">
    <flux:sidebar.profile
        :name="$currentUser?->name"
        :initials="$currentUser?->initials()"
        icon:trailing="chevrons-up-down"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="$currentUser?->name"
                :initials="$currentUser?->initials()"
            />
            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ $currentUser?->name }}</flux:heading>
                <flux:text class="truncate">{{ $currentUser?->email }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.radio.group>
            @if(auth('student')->check())
                <flux:menu.item :href="route('student.settings')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>
            @else
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('Settings') }}
                </flux:menu.item>
            @endif
            @if(auth('student')->check())
                <form method="POST" action="{{ route('student.logout') }}" class="w-full">
            @else
                <form method="POST" action="{{ route('logout') }}" class="w-full">
            @endif
                @csrf
                <flux:menu.item
                    as="button"
                    type="submit"
                    icon="arrow-right-start-on-rectangle"
                    class="w-full cursor-pointer"
                    data-test="logout-button"
                >
                    {{ __('Log out') }}
                </flux:menu.item>
            </form>
        </flux:menu.radio.group>
    </flux:menu>
</flux:dropdown>
