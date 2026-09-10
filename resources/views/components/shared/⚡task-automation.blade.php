<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\TaskCategory;
use App\Models\TaskSeries;
use App\Models\TaskTemplate;
use App\Services\TaskFollowUpService;
use App\Support\Scope;
use App\Support\TaskAssignment;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The two things that make tasks happen without anybody remembering them.
 *
 * A pattern raises its own work — the weekly report every Thursday, the monthly
 * return on the first — and a template raises a whole set at once, with dates
 * written relative to the day it is applied, so «three days before» stays three
 * days before whichever term it is.
 *
 * They sit on one screen because they are both answers to «I am tired of typing
 * this again», and because a man who has just written one usually wants the
 * other.
 */
new class extends Component
{
    public string $asRole = '';

    public string $tab = 'series';

    // نمط جديد
    public string $title = '';

    public string $every = TaskSeries::WEEKLY;

    public int $onWeekday = 1;

    public int $onDay = 1;

    public string $startsOn = '';

    public string $assignMode = 'person';

    public ?string $role = null;

    public string $scopeType = 'stages';

    /** @var array<int, int> */
    public array $scopeIds = [];

    public ?int $personId = null;

    // قالب جديد
    public string $templateName = '';

    public ?int $applyingTemplate = null;

    public string $applyOn = '';

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
        $this->startsOn = now('Asia/Riyadh')->toDateString();
        $this->applyOn = $this->startsOn;
        $this->role = TaskAssignment::rolesAssignableBy($this->asRole)[0] ?? null;
    }

    private function scope(): Scope
    {
        return Scope::forRole($this->asRole);
    }

    private function reader(): ?App\Models\User
    {
        return auth($this->asRole)->user();
    }

    /** @return Collection<int, TaskSeries> */
    #[Computed]
    public function series(): Collection
    {
        return TaskSeries::orderByDesc('is_active')->orderBy('title')->get();
    }

    /** @return Collection<int, TaskTemplate> */
    #[Computed]
    public function templates(): Collection
    {
        return TaskTemplate::with('items')->orderBy('name')->get();
    }

    /** The offices this reader may put work on. */
    #[Computed]
    public function assignableRoles(): array
    {
        return TaskAssignment::rolesAssignableBy($this->asRole);
    }

    /** @return Collection<int, Stage|Circle> */
    #[Computed]
    public function reachChoices(): Collection
    {
        return $this->scopeType === 'circles'
            ? $this->scope()->circleQuery()->orderBy('name')->get()
            : Stage::whereIn('id', $this->scope()->stageIds() ?? Stage::pluck('id'))->orderBy('name')->get();
    }

    /** Everyone the chosen office holds, within the chosen reach. */
    #[Computed]
    public function people(): Collection
    {
        if (! $this->role) {
            return collect();
        }

        return app(TaskFollowUpService::class)
            ->peopleHolding($this->role, $this->scopeType, $this->scopeIds)
            ->sortBy('name')
            ->values();
    }

    public function saveSeries(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'every' => ['required', 'in:daily,weekly,monthly'],
            'startsOn' => ['required', 'date'],
            'role' => ['nullable', 'string'],
        ], [], ['title' => __('العنوان'), 'startsOn' => __('تاريخ البداية')]);

        abort_unless(TaskAssignment::mayAssign($this->asRole), 403);
        abort_unless(in_array($this->role, $this->assignableRoles, true), 403);

        // A task raised for nobody is a task nobody answers for, and it would be
        // raised again every week — so the pattern is refused before it starts
        // rather than discovered as a column of ownerless rows.
        if ($this->assignMode === 'person' && ! $this->personId) {
            Flux::toast(__('اختر الشخص الذي يُسند إليه، أو اجعله «كلّ من يحمل دوراً».'), variant: 'warning');

            return;
        }

        if ($this->assignMode === 'role' && $this->scopeIds === []) {
            Flux::toast(__('اختر ما يشمله النمط، ولو واحداً.'), variant: 'warning');

            return;
        }

        TaskSeries::create([
            'title' => $this->title,
            'every' => $this->every,
            'on_weekday' => $this->every === TaskSeries::WEEKLY ? $this->onWeekday : null,
            'on_day' => $this->every === TaskSeries::MONTHLY ? $this->onDay : null,
            'starts_on' => $this->startsOn,
            'assigned_to_type' => $this->assignMode === 'person' ? $this->role : null,
            'assigned_to_id' => $this->assignMode === 'person' ? $this->personId : null,
            'assign_to_role' => $this->assignMode === 'role' ? $this->role : null,
            'assign_scope_type' => $this->assignMode === 'role' ? $this->scopeType : null,
            'assign_scope_ids' => $this->assignMode === 'role' ? array_map('intval', $this->scopeIds) : null,
            'created_by_type' => $this->asRole,
            'created_by_id' => $this->reader()?->id,
        ]);

        $this->reset(['title', 'personId', 'scopeIds']);
        unset($this->series);

        Flux::toast(__('حُفظ النمط. يبدأ برفع مهامّه من أوّل يومٍ يوافقه.'), variant: 'success');
    }

    public function toggleSeries(int $id): void
    {
        $series = TaskSeries::findOrFail($id);
        $series->update(['is_active' => ! $series->is_active]);

        unset($this->series);
    }

    public function deleteSeries(int $id): void
    {
        // The tasks it already raised are somebody's work and stay; only the
        // pattern stops.
        TaskSeries::whereKey($id)->delete();

        unset($this->series);

        Flux::toast(__('حُذف النمط. وما رفعه من مهامّ باقٍ.'), variant: 'success');
    }

    public function raiseToday(): void
    {
        $raised = app(TaskFollowUpService::class)->raiseDue();

        Flux::toast(
            $raised > 0
                ? __('رُفعت :n مهمة لهذا اليوم.', ['n' => $raised])
                : __('لا شيء يستحقّ الرفع اليوم — أو رُفع سلفاً.'),
            variant: 'success',
        );
    }

    public function saveTemplate(): void
    {
        $this->validate(['templateName' => ['required', 'string', 'max:255']], [], ['templateName' => __('الاسم')]);

        TaskTemplate::create([
            'name' => $this->templateName,
            'created_by_type' => $this->asRole,
            'created_by_id' => $this->reader()?->id,
        ]);

        $this->reset('templateName');
        unset($this->templates);

        Flux::toast(__('أُنشئ القالب. أضف بنوده الآن.'), variant: 'success');
    }

    public function addItem(int $templateId, string $title, int $offset = 0, ?string $role = null): void
    {
        $template = TaskTemplate::findOrFail($templateId);

        if (trim($title) === '') {
            return;
        }

        $template->items()->create([
            'title' => trim($title),
            'due_offset_days' => $offset,
            'assign_to_role' => $role,
            'sort_order' => (int) $template->items()->max('sort_order') + 1,
        ]);

        unset($this->templates);
    }

    public function removeItem(int $itemId): void
    {
        App\Models\TaskTemplateItem::whereKey($itemId)->delete();

        unset($this->templates);
    }

    public function applyTemplate(): void
    {
        $template = TaskTemplate::with('items')->findOrFail($this->applyingTemplate);

        $this->validate(['applyOn' => ['required', 'date']], [], ['applyOn' => __('يوم التطبيق')]);

        $made = app(TaskFollowUpService::class)->applyTemplate(
            $template,
            Carbon::parse($this->applyOn),
            $this->reader(),
            $this->asRole,
            $this->scopeType,
            array_map('intval', $this->scopeIds),
        );

        $this->applyingTemplate = null;

        Flux::toast(__('رُفعت :n مهمة من القالب.', ['n' => count($made)]), variant: 'success');
    }
}; ?>

<div class="space-y-6">
    <x-section-heading icon="arrow-path">
        {{ __('المهام التلقائية') }}

        <x-slot:detail>
            {{ __('نمطٌ يرفع مهامّه بنفسه، وقالبٌ يرفع مجموعةً كاملة بمواعيد تُحسب من يوم تطبيقه.') }}
        </x-slot:detail>
    </x-section-heading>

    <div class="flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-800">
        @foreach (['series' => __('الأنماط المتكرّرة'), 'templates' => __('القوالب')] as $key => $label)
            <button wire:click="$set('tab', '{{ $key }}')" wire:key="tab-{{ $key }}"
                class="px-4 py-2.5 text-sm font-bold border-b-2 transition-colors
                    {{ $tab === $key ? 'border-maroon text-maroon dark:text-red-secondary' : 'border-transparent text-zinc-500' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'series')
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- نمط جديد --}}
            <flux:card>
                <flux:heading size="lg">{{ __('نمط جديد') }}</flux:heading>

                <form wire:submit="saveSeries" class="mt-4 space-y-4">
                    <flux:field>
                        <flux:label>{{ __('العنوان') }}</flux:label>
                        <flux:input wire:model="title" :placeholder="__('مثال: التقرير الأسبوعي')" />
                        <flux:error name="title" />
                    </flux:field>

                    <div class="grid grid-cols-2 gap-3">
                        <flux:field>
                            <flux:label>{{ __('التكرار') }}</flux:label>
                            <flux:select wire:model.live="every">
                                <flux:select.option value="daily">{{ __('كل يوم') }}</flux:select.option>
                                <flux:select.option value="weekly">{{ __('كل أسبوع') }}</flux:select.option>
                                <flux:select.option value="monthly">{{ __('كل شهر') }}</flux:select.option>
                            </flux:select>
                        </flux:field>

                        @if ($every === 'weekly')
                            <flux:field>
                                <flux:label>{{ __('اليوم') }}</flux:label>
                                <flux:select wire:model="onWeekday">
                                    @foreach ([1 => 'الأحد', 2 => 'الاثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء', 5 => 'الخميس', 6 => 'الجمعة', 7 => 'السبت'] as $n => $day)
                                        <flux:select.option value="{{ $n }}">{{ $day }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        @elseif ($every === 'monthly')
                            <flux:field>
                                <flux:label>{{ __('يوم الشهر') }}</flux:label>
                                <flux:input type="number" min="1" max="28" wire:model="onDay" />
                            </flux:field>
                        @endif
                    </div>

                    <flux:field>
                        <flux:label>{{ __('يبدأ من') }}</flux:label>
                        <flux:input type="date" wire:model="startsOn" />
                        <flux:error name="startsOn" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('على من؟') }}</flux:label>
                        <flux:select wire:model.live="assignMode">
                            <flux:select.option value="person">{{ __('شخصٌ بعينه') }}</flux:select.option>
                            <flux:select.option value="role">{{ __('كلّ من يحمل دوراً') }}</flux:select.option>
                        </flux:select>
                        <flux:description>
                            {{ __('«كلّ من يحمل دوراً» يرفع مهمّةً لكلّ واحدٍ منهم — فمهمّةٌ يتقاسمها ستّة هي مهمّةٌ لم يُنجزها أحد.') }}
                        </flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('الدور') }}</flux:label>
                        <flux:select wire:model.live="role">
                            @foreach ($this->assignableRoles as $r)
                                <flux:select.option value="{{ $r }}">{{ \App\Support\RoleTitle::of($r) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    @if ($assignMode === 'person')
                        <flux:field>
                            <flux:label>{{ __('الشخص') }}</flux:label>
                            <flux:select wire:model="personId">
                                <flux:select.option value="">{{ __('اختر') }}</flux:select.option>
                                @foreach ($this->people as $person)
                                    <flux:select.option value="{{ $person->id }}">{{ $person->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @else
                        <flux:field>
                            <flux:label>{{ __('ضمن') }}</flux:label>
                            <flux:select wire:model.live="scopeType">
                                <flux:select.option value="stages">{{ __('برامج') }}</flux:select.option>
                                <flux:select.option value="circles">{{ __('دفعات') }}</flux:select.option>
                            </flux:select>
                        </flux:field>

                        <div class="max-h-36 overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 space-y-1.5">
                            @forelse ($this->reachChoices as $choice)
                                <flux:checkbox wire:model="scopeIds" value="{{ $choice->id }}" :label="$choice->name" />
                            @empty
                                <p class="text-sm text-zinc-400">{{ __('لا شيء في متناولك.') }}</p>
                            @endforelse
                        </div>

                        <p class="text-xs text-zinc-500">
                            {{ __('سيرفع لـ :n شخصاً.', ['n' => $this->people->count()]) }}
                        </p>
                    @endif

                    <flux:button type="submit" variant="primary" class="w-full">{{ __('احفظ النمط') }}</flux:button>
                </form>
            </flux:card>

            {{-- الأنماط القائمة --}}
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ __('الأنماط القائمة') }}</span>
                    <flux:button size="sm" variant="filled" icon="play" wire:click="raiseToday">
                        {{ __('ارفع مهام اليوم') }}
                    </flux:button>
                </div>

                @forelse ($this->series as $one)
                    <flux:card class="flex flex-wrap items-center justify-between gap-3" wire:key="sr-{{ $one->id }}">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-zinc-800 dark:text-zinc-100">{{ $one->title }}</span>
                                @unless ($one->is_active)
                                    <flux:badge size="sm" color="zinc">{{ __('موقوف') }}</flux:badge>
                                @endunless
                            </div>
                            <div class="text-xs text-zinc-500 mt-0.5">
                                {{ $one->say() }}
                                @if ($one->assign_to_role)
                                    · {{ __('لكلّ :role', ['role' => \App\Support\RoleTitle::of($one->assign_to_role)]) }}
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <flux:button size="sm" variant="ghost" wire:click="toggleSeries({{ $one->id }})">
                                {{ $one->is_active ? __('أوقفه') : __('شغّله') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" icon="trash"
                                class="text-red-secondary"
                                wire:click="deleteSeries({{ $one->id }})"
                                wire:confirm="{{ __('سيتوقّف النمط. وما رفعه من مهامّ يبقى كما هو. متابعة؟') }}" />
                        </div>
                    </flux:card>
                @empty
                    <x-empty-note icon="arrow-path" :title="__('لا أنماط بعد')">
                        {{ __('النمط يرفع مهمّته في يومها بلا أن يتذكّرها أحد.') }}
                    </x-empty-note>
                @endforelse
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <flux:card>
                <flux:heading size="lg">{{ __('قالب جديد') }}</flux:heading>

                <form wire:submit="saveTemplate" class="mt-4 flex gap-2">
                    <flux:input wire:model="templateName" :placeholder="__('مثال: افتتاح الفصل')" />
                    <flux:button type="submit" variant="primary" icon="plus" />
                </form>
                <flux:error name="templateName" />
            </flux:card>

            <div class="space-y-3">
                @forelse ($this->templates as $template)
                    <flux:card class="space-y-3" wire:key="tp-{{ $template->id }}">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-zinc-800 dark:text-zinc-100">{{ $template->name }}</span>
                            <flux:button size="sm" variant="filled"
                                wire:click="$set('applyingTemplate', {{ $template->id }})">
                                {{ __('طبّقه') }}
                            </flux:button>
                        </div>

                        @foreach ($template->items as $item)
                            <div class="flex items-center gap-2 text-sm group" wire:key="ti-{{ $item->id }}">
                                <span class="text-zinc-700 dark:text-zinc-200">{{ $item->title }}</span>
                                <span class="text-xs text-zinc-400">· {{ $item->whenSaid() }}</span>
                                @if ($item->assign_to_role)
                                    <flux:badge size="sm">{{ \App\Support\RoleTitle::of($item->assign_to_role) }}</flux:badge>
                                @endif
                                <button type="button" wire:click="removeItem({{ $item->id }})"
                                    class="ms-auto opacity-0 group-hover:opacity-100 text-zinc-300 hover:text-rose-500">
                                    <flux:icon icon="x-mark" class="size-3.5" />
                                </button>
                            </div>
                        @endforeach

                        <form x-data="{ title: '', offset: 0, role: '' }"
                            @submit.prevent="$wire.addItem({{ $template->id }}, title, parseInt(offset), role || null); title = ''"
                            class="flex flex-wrap gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                            <flux:input size="sm" x-model="title" :placeholder="__('بند جديد')" class="flex-1 min-w-40" />
                            <flux:input size="sm" type="number" x-model="offset" class="w-24"
                                :placeholder="__('± أيام')" />
                            <flux:button size="sm" type="submit" icon="plus" />
                        </form>
                    </flux:card>
                @empty
                    <x-empty-note icon="rectangle-stack" :title="__('لا قوالب بعد')">
                        {{ __('القالب يرفع مجموعةً كاملة بمواعيد تُحسب من يوم تطبيقه.') }}
                    </x-empty-note>
                @endforelse
            </div>
        </div>

        @if ($applyingTemplate)
            <flux:card class="space-y-4">
                <flux:heading size="lg">{{ __('تطبيق القالب') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('يوم التطبيق') }}</flux:label>
                    <flux:input type="date" wire:model="applyOn" />
                    <flux:description>
                        {{ __('المواعيد تُحسب منه: «قبله بثلاثة» تبقى قبله بثلاثة أيّ يومٍ اخترت.') }}
                    </flux:description>
                    <flux:error name="applyOn" />
                </flux:field>

                <div class="flex gap-2">
                    <flux:button variant="primary" wire:click="applyTemplate">{{ __('ارفع المهام') }}</flux:button>
                    <flux:button variant="ghost" wire:click="$set('applyingTemplate', null)">{{ __('إلغاء') }}</flux:button>
                </div>
            </flux:card>
        @endif
    @endif
</div>
