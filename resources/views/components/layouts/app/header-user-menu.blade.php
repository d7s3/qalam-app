@php
    $currentUser = auth('student')->check() ? auth('student')->user() : auth()->user();
@endphp
<flux:dropdown position="top" align="end">
    <flux:profile
        :initials="$currentUser?->initials()"
        icon-trailing="chevron-down"
    />

    <flux:menu>
        <flux:menu.radio.group>
            <div class="p-0 text-sm font-normal text-right">
                <div class="flex items-center gap-2 px-1 py-1.5 text-right text-sm">
                    <flux:avatar
                        :name="$currentUser?->name"
                        :initials="$currentUser?->initials()"
                    />

                    <div class="grid flex-1 text-right text-sm leading-tight">
                        <flux:heading class="truncate">{{ $currentUser?->name }}</flux:heading>
                        <flux:text class="truncate">{{ $currentUser?->email }}</flux:text>
                    </div>
                </div>
            </div>
        </flux:menu.radio.group>

        <flux:menu.separator />

        <flux:menu.radio.group>
            @if(auth('student')->check())
                <flux:menu.item :href="route('student.settings')" icon="cog" wire:navigate>
                    {{ __('الإعدادات') }}
                </flux:menu.item>
            @else
                <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                    {{ __('الإعدادات') }}
                </flux:menu.item>
            @endif
        </flux:menu.radio.group>

        <flux:menu.separator />

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
                {{ __('تسجيل الخروج') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
