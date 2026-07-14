@php
    $staffUser = auth('staff')->user();
    $staffUnreadMessages = \App\Services\MessagingService::unreadCountFor('staff', $staffUser->id);

    $grantedScreens = $staffUser->staff_role_id
        ? \App\Models\RoleScreenPermission::where('role_id', $staffUser->staff_role_id)
            ->with('screen')
            ->get()
            ->pluck('screen')
            ->filter()
        : collect();
@endphp

<flux:sidebar.item icon="home" :href="route('staff.dashboard')" :current="request()->routeIs('staff.dashboard')"
    wire:navigate>
    الرئيسية
</flux:sidebar.item>
<flux:sidebar.item icon="envelope" :href="route('staff.messages')" :current="request()->routeIs('staff.messages')"
    :badge="$staffUnreadMessages > 0 ? $staffUnreadMessages : null" badge-color="rose" wire:navigate>
    الرسائل
</flux:sidebar.item>

@if($grantedScreens->isNotEmpty())
    <flux:sidebar.group heading="{{ __('صلاحيات إضافية لدورك') }}" class="grid">
        @foreach($grantedScreens as $screen)
            <div class="flex items-center justify-between px-2.5 py-2 text-sm text-white/70" wire:key="staff-screen-{{ $screen->id }}">
                <span>{{ $screen->label }}</span>
                <flux:badge color="amber" size="sm">{{ __('قريباً') }}</flux:badge>
            </div>
        @endforeach
    </flux:sidebar.group>
@endif
