{{--
    The screens this office carries from the ones beneath it.

    Kept in a group of its own rather than mixed into the reader's own pages, so
    it is plain that these are held by seniority and not part of the office
    itself — and so a supervisor can tell at a glance which of his pages are the
    teacher's, which is what he is standing in for when he opens one.
--}}
@php
    $role = \App\Support\Scope::resolveRole();
    $carried = \App\Support\RoleHierarchy::inheritedBy($role);

    $held = $carried === [] || ! \Illuminate\Support\Facades\Route::has($role.'.held')
        ? collect()
        : \App\Models\Screen::query()
            ->whereHas('ownerRole', fn ($q) => $q->whereIn('key', $carried))
            ->where('is_protected', false)
            ->orderBy('group_label')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($screen) => \App\Support\Access::canSee(auth($role)->user(), $role, $screen->route_name))
            // A page the reader already owns under his own prefix is not shown
            // twice; his own is the one that belongs in his navigation.
            ->reject(fn ($screen) => \App\Models\Screen::where('route_name', $role.'.'.\Illuminate\Support\Str::after($screen->route_name, '.'))->exists())
            ->values();
@endphp

@if ($held->isNotEmpty())
    <flux:sidebar.group heading="{{ __('صلاحيات يحملها منصبك') }}" class="grid" expandable :expanded="false">
        @foreach ($held as $screen)
            <flux:sidebar.item wire:key="held-{{ $screen->id }}"
                class="[&_svg]:bg-[#64748b] hover:[&_svg]:bg-[#475569]"
                icon="arrow-top-right-on-square"
                :href="route($role.'.held', ['screen' => $screen->route_name])"
                :current="request()->routeIs($role.'.held') && request()->route('screen') === $screen->route_name"
                wire:navigate>
                {{ $screen->label }}
            </flux:sidebar.item>
        @endforeach
    </flux:sidebar.group>
@endif
