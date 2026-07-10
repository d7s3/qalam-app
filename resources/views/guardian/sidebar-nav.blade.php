@php
    $guardianUnreadMessages = \App\Services\MessagingService::unreadCountFor('guardian', auth('guardian')->id());
@endphp

<flux:sidebar.group :heading="__('Platform')" class="grid">
    <flux:sidebar.item icon="home" :href="route('guardian.dashboard')" :current="request()->routeIs('guardian.dashboard')" wire:navigate>
        {{ __('الرئيسية') }}
    </flux:sidebar.item>
    @if(\App\Support\RolePages::isEnabled('guardian', 'guardian.messages'))
        <flux:sidebar.item icon="envelope" :href="route('guardian.messages')" :current="request()->routeIs('guardian.messages')"
            :badge="$guardianUnreadMessages > 0 ? $guardianUnreadMessages : null" badge-color="rose" wire:navigate>
            {{ __('الرسائل') }}
        </flux:sidebar.item>
    @endif
    @if(\App\Support\RolePages::isEnabled('guardian', 'guardian.challenges'))
        <flux:sidebar.item icon="trophy" :href="route('guardian.challenges')" :current="request()->routeIs('guardian.challenges')" wire:navigate>
            {{ __('المكافآت التحفيزية') }}
        </flux:sidebar.item>
    @endif
</flux:sidebar.group>

<flux:sidebar.group heading="متابعة الأبناء" class="grid">
    @foreach(auth()->guard('guardian')->user()->students()->with('circle')->get() as $sidebarStudent)
        <flux:sidebar.item
            icon="academic-cap"
            :href="route('guardian.student', $sidebarStudent->id)"
            :current="request()->routeIs('guardian.student*') && request()->route('id') == $sidebarStudent->id"
            wire:navigate>
            {{ $sidebarStudent->name }}
        </flux:sidebar.item>
    @endforeach
</flux:sidebar.group>
