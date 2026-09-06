<?php

use App\Models\Role;
use App\Models\RoleScreenPermission;
use App\Models\Screen;
use App\Models\Stage;
use App\Models\StageScreenPermission;
use App\Support\RoleHierarchy;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * What each role may open inside one programme.
 *
 * The central grant on `manager.role-permissions` says what a job needs across
 * the academy; this says what it needs here. Only differences are kept, so the
 * ordinary state of a page is "follows the role" and costs nothing to express —
 * and a programme created tomorrow works without anyone visiting this screen.
 */
new class extends Component
{
    public ?int $activeStageId = null;

    public ?int $activeRoleId = null;

    public function mount(): void
    {
        $this->activeStageId = Stage::orderBy('name')->value('id');
        $this->activeRoleId = Role::where('key', 'teacher')->value('id')
            ?? Role::orderBy('id')->value('id');
    }

    public function setStage(int $stageId): void
    {
        if (Stage::whereKey($stageId)->exists()) {
            $this->activeStageId = $stageId;
        }
    }

    public function setRole(int $roleId): void
    {
        if (Role::whereKey($roleId)->exists()) {
            $this->activeRoleId = $roleId;
        }
    }

    /**
     * Set one screen's word for this programme and role.
     *
     * `inherit` removes the row rather than storing a third value: the absence
     * is the state, and keeping it that way means a later central grant reaches
     * this programme instead of being silently overruled by a stale copy.
     */
    public function setState(int $screenId, string $state): void
    {
        $stage = Stage::find($this->activeStageId);
        $role = Role::find($this->activeRoleId);
        $screen = Screen::find($screenId);

        if (! $stage || ! $role || ! $screen || $screen->is_protected) {
            return;
        }

        $existing = StageScreenPermission::where('stage_id', $stage->id)
            ->where('role_id', $role->id)
            ->where('screen_id', $screen->id)
            ->first();

        if ($state === 'inherit') {
            $existing?->delete();

            Flux::toast(__('رجعت تتبع الدور'), variant: 'success');

            return;
        }

        $allowed = $state === 'on';

        StageScreenPermission::updateOrCreate(
            ['stage_id' => $stage->id, 'role_id' => $role->id, 'screen_id' => $screen->id],
            ['is_allowed' => $allowed, 'set_by' => Auth::guard('manager')->id()],
        );

        Flux::toast(
            $allowed ? __('فُعّلت في هذا البرنامج') : __('عُطّلت في هذا البرنامج'),
            variant: 'success',
        );
    }

    public function with(): array
    {
        $stages = Stage::orderBy('name')->get();
        $roles = Role::where('is_active', true)->orderByDesc('is_system')->orderBy('id')->get();

        $activeStage = $stages->firstWhere('id', $this->activeStageId) ?? $stages->first();
        $activeRole = $roles->firstWhere('id', $this->activeRoleId) ?? $roles->first();

        // The pages this role could ever hold here: its own, and those of every
        // role it carries — the same set its sidebar is drawn from.
        $carried = $activeRole ? RoleHierarchy::chainFor($activeRole->key) : [];
        $ownerIds = Role::whereIn('key', $carried)->pluck('id');

        $screens = Screen::whereIn('owner_role_id', $ownerIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('group_label');

        $central = $activeRole
            ? RoleScreenPermission::whereIn('role_id', Role::whereIn('key', $carried)->pluck('id'))
                ->pluck('screen_id')
                ->all()
            : [];

        $exceptions = ($activeStage && $activeRole)
            ? StageScreenPermission::where('stage_id', $activeStage->id)
                ->where('role_id', $activeRole->id)
                ->pluck('is_allowed', 'screen_id')
                ->map(fn ($allowed) => (bool) $allowed)
                ->all()
            : [];

        return [
            'stages' => $stages,
            'roles' => $roles,
            'activeStage' => $activeStage,
            'activeRole' => $activeRole,
            'screens' => $screens,
            'central' => $central,
            'exceptions' => $exceptions,
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
            {{ __('صلاحيات البرامج') }}
        </flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('ادخل على برنامج، واختر دوراً، وقرّر لكل صفحة: تتبع الدور، أو تُفعّل هنا، أو تُعطّل هنا. ما تتركه على «يتبع الدور» يتغيّر تلقائياً مع صلاحيات الدور العامة.') }}
        </flux:subheading>
    </div>

    @if($stages->isEmpty())
        <flux:card>
            <flux:text>{{ __('لا توجد برامج بعد. أنشئ برنامجاً أولاً من صفحة البرامج.') }}</flux:text>
        </flux:card>
    @else
        <flux:card class="space-y-4">
            <div>
                <flux:label class="mb-2 block">{{ __('البرنامج') }}</flux:label>
                <div class="flex items-center gap-2 flex-wrap">
                    @foreach($stages as $stage)
                        <button
                            wire:click="setStage({{ $stage->id }})"
                            wire:key="stage-{{ $stage->id }}"
                            class="px-4 py-2 text-sm font-bold rounded-lg border transition-colors
                                {{ $activeStage?->id === $stage->id
                                    ? 'bg-maroon text-white border-maroon'
                                    : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:border-maroon' }}">
                            {{ $stage->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div>
                <flux:label class="mb-2 block">{{ __('الدور') }}</flux:label>
                <div class="flex items-center gap-2 flex-wrap">
                    @foreach($roles as $role)
                        <button
                            wire:click="setRole({{ $role->id }})"
                            wire:key="role-{{ $role->id }}"
                            class="px-4 py-2 text-sm font-bold rounded-lg border transition-colors
                                {{ $activeRole?->id === $role->id
                                    ? 'bg-maroon text-white border-maroon'
                                    : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:border-maroon' }}">
                            {{ $role->label }}
                        </button>
                    @endforeach
                </div>
            </div>
        </flux:card>

        @if($activeStage && $activeRole)
            <div class="space-y-4">
                @forelse($screens as $groupLabel => $groupScreens)
                    <flux:card>
                        <flux:heading size="sm" class="mb-4">{{ $groupLabel ?: __('عام') }}</flux:heading>
                        <div class="divide-y divide-zinc-50 dark:divide-zinc-800/60">
                            @foreach($groupScreens as $screen)
                                @php
                                    $written = $exceptions[$screen->id] ?? null;
                                    $inherited = in_array($screen->id, $central, true);
                                    $state = $written === null ? 'inherit' : ($written ? 'on' : 'off');
                                @endphp
                                <div class="flex items-center justify-between gap-4 py-3"
                                     wire:key="row-{{ $activeStage->id }}-{{ $activeRole->id }}-{{ $screen->id }}">
                                    <div class="min-w-0">
                                        <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $screen->label }}</div>
                                        <div class="text-xs text-zinc-400 truncate">{{ $screen->route_name }}</div>
                                    </div>

                                    @if($screen->is_protected)
                                        <flux:badge color="zinc" size="sm">{{ __('دائماً مفعّلة') }}</flux:badge>
                                    @else
                                        <div class="flex items-center rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden shrink-0">
                                            <button
                                                wire:click="setState({{ $screen->id }}, 'inherit')"
                                                class="px-3 py-1.5 text-xs font-bold transition-colors
                                                    {{ $state === 'inherit'
                                                        ? 'bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-100'
                                                        : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                                {{ $inherited ? __('يتبع الدور (مفعّلة)') : __('يتبع الدور (معطّلة)') }}
                                            </button>
                                            <button
                                                wire:click="setState({{ $screen->id }}, 'on')"
                                                class="px-3 py-1.5 text-xs font-bold border-r border-zinc-200 dark:border-zinc-700 transition-colors
                                                    {{ $state === 'on'
                                                        ? 'bg-emerald-600 text-white'
                                                        : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                                {{ __('تفعيل') }}
                                            </button>
                                            <button
                                                wire:click="setState({{ $screen->id }}, 'off')"
                                                class="px-3 py-1.5 text-xs font-bold border-r border-zinc-200 dark:border-zinc-700 transition-colors
                                                    {{ $state === 'off'
                                                        ? 'bg-rose-600 text-white'
                                                        : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                                                {{ __('تعطيل') }}
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </flux:card>
                @empty
                    <flux:card>
                        <flux:text>{{ __('لا توجد صفحات لهذا الدور.') }}</flux:text>
                    </flux:card>
                @endforelse
            </div>
        @endif
    @endif
</div>
