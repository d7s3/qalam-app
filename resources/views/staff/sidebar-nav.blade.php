@php
    $staffUser = auth('staff')->user();
    $staffUnreadMessages = \App\Services\MessagingService::unreadCountFor('staff', $staffUser->id);

    // The pages this custom role was granted. Filtered through the one access
    // answer rather than read straight from the grants, so a page closed for
    // this person, or inside his programme, does not appear here anyway.
    $grantedScreens = $staffUser->staff_role_id
        ? \App\Models\RoleScreenPermission::where('role_id', $staffUser->staff_role_id)
            ->with('screen')
            ->get()
            ->pluck('screen')
            ->filter()
            ->filter(fn ($screen) => \App\Support\Access::canSee($staffUser, 'staff', $screen->route_name))
            ->values()
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
            <flux:sidebar.item wire:key="staff-screen-{{ $screen->id }}"
                icon="arrow-top-right-on-square"
                :href="route('staff.held', ['screen' => $screen->route_name])"
                :current="request()->routeIs('staff.held') && request()->route('screen') === $screen->route_name"
                wire:navigate>
                {{ $screen->label }}
            </flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
@endif
