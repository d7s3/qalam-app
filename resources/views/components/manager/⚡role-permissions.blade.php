<?php

use App\Models\DisabledRolePage;
use App\Support\RolePages;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    public string $activeRole = 'teacher';

    protected const ROLE_LABELS = [
        'teacher' => 'المعلم',
        'supervisor' => 'المشرف',
        'guardian' => 'ولي الأمر',
        'student' => 'الطالب',
    ];

    public function setActiveRole(string $role): void
    {
        if (in_array($role, RolePages::roles(), true)) {
            $this->activeRole = $role;
        }
    }

    public function toggle(string $role, string $route): void
    {
        if (! in_array($role, RolePages::roles(), true) || RolePages::isProtected($route)) {
            return;
        }

        $existing = DisabledRolePage::where('role', $role)->where('route', $route)->first();

        if ($existing) {
            $existing->delete();
            Flux::toast(__('تم تفعيل الصفحة'), variant: 'success');
        } else {
            DisabledRolePage::create([
                'role' => $role,
                'route' => $route,
                'disabled_by' => Auth::guard('manager')->id(),
            ]);
            Flux::toast(__('تم تعطيل الصفحة'), variant: 'success');
        }
    }

    public function with(): array
    {
        $disabledForActiveRole = DisabledRolePage::where('role', $this->activeRole)->pluck('route')->all();

        $roleCounts = collect(RolePages::roles())->mapWithKeys(function (string $role) {
            $total = collect(RolePages::pagesFor($role))->collapse()->count();
            $disabled = DisabledRolePage::where('role', $role)->count();

            return [$role => ['total' => $total, 'disabled' => $disabled]];
        });

        return [
            'roles' => RolePages::roles(),
            'roleLabels' => self::ROLE_LABELS,
            'pages' => RolePages::pagesFor($this->activeRole),
            'disabledForActiveRole' => $disabledForActiveRole,
            'roleCounts' => $roleCounts,
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
            {{ __('صلاحيات الصفحات') }}
        </flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('تحكّم في الصفحات الظاهرة لكل دور. الصفحة المعطّلة تختفي من القائمة الجانبية ولا يمكن الوصول لها حتى بالرابط المباشر.') }}
        </flux:subheading>
    </div>

    <div class="flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-800 overflow-x-auto">
        @foreach($roles as $role)
            <button
                wire:click="setActiveRole('{{ $role }}')"
                wire:key="role-tab-{{ $role }}"
                class="px-4 py-2.5 text-sm font-bold whitespace-nowrap border-b-2 transition-colors flex items-center gap-2
                    {{ $activeRole === $role ? 'border-maroon text-maroon dark:text-red-secondary' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                {{ $roleLabels[$role] }}
                @if($roleCounts[$role]['disabled'] > 0)
                    <flux:badge color="amber" size="sm">{{ $roleCounts[$role]['disabled'] }}</flux:badge>
                @endif
            </button>
        @endforeach
    </div>

    <div class="space-y-6">
        @foreach($pages as $group => $groupPages)
            <flux:card>
                <flux:heading size="sm" class="mb-4">{{ $group }}</flux:heading>
                <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                    @foreach($groupPages as $routeName => $label)
                        @php
                            $isProtected = \App\Support\RolePages::isProtected($routeName);
                            $isDisabled = in_array($routeName, $disabledForActiveRole, true);
                        @endphp
                        <div class="flex items-center justify-between py-3" wire:key="page-{{ $activeRole }}-{{ $routeName }}">
                            <div>
                                <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $label }}</div>
                                <div class="text-xs text-zinc-400">{{ $routeName }}</div>
                            </div>

                            @if($isProtected)
                                <flux:badge color="zinc" size="sm">{{ __('دائماً مفعّلة') }}</flux:badge>
                            @else
                                <flux:switch
                                    :checked="! $isDisabled"
                                    wire:click="toggle('{{ $activeRole }}', '{{ $routeName }}')" />
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endforeach
    </div>
</div>
