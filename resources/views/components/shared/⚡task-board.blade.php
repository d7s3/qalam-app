<?php

use App\Models\Task;
use App\Models\TaskActivity;
use App\Services\TaskFollowUpService;
use App\Support\Scope;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Where work is followed rather than listed.
 *
 * The existing screens list tasks and let them be ticked, which answers «what
 * is outstanding» and nothing else. Two questions matter more to whoever has to
 * answer for the work: where has each task stopped, and what has happened to it
 * since it was asked for.
 *
 * The board answers the first by putting the stages side by side — and
 * «waiting» earns its column, because a task nobody has touched for a fortnight
 * and a task waiting on somebody else look identical in a list and are not the
 * same problem. The panel answers the second: the steps, what people said, and
 * the record of every hand that touched it.
 *
 * The reach is `Scope`'s, as everywhere: a supervisor's board carries his
 * programme's work, a manager over one programme carries that one's.
 */
new class extends Component
{
    /** The office this board is being read from, pinned at mount. */
    public string $asRole = '';

    public ?int $openTask = null;

    public string $newStep = '';

    public string $newComment = '';

    /** Show only what one person owes, when a name is picked. */
    public ?int $onlyFor = null;

    public function mount(): void
    {
        // Pinned here because a Livewire update's route is `livewire.update`,
        // which names no office at all — so the reach must be settled while a
        // real page is still being asked for.
        $this->asRole = Scope::resolveRole();
    }

    private function scope(): Scope
    {
        return Scope::forRole($this->asRole);
    }

    /** @return Collection<string, Collection<int, Task>> */
    #[Computed]
    public function columns(): Collection
    {
        $tasks = $this->scope()
            ->applyToTasks(Task::query()->with(['steps', 'category']))
            ->when($this->onlyFor, fn ($q) => $q->where('assigned_to_id', $this->onlyFor))
            ->orderByRaw('due_date is null, due_date')
            ->get();

        return collect(Task::STAGES)->mapWithKeys(fn (string $label, string $key) => [
            $key => $tasks->where('stage', $key)->values(),
        ]);
    }

    #[Computed]
    public function task(): ?Task
    {
        if (! $this->openTask) {
            return null;
        }

        return $this->scope()
            ->applyToTasks(Task::query()->with(['steps', 'comments', 'activities', 'category']))
            ->whereKey($this->openTask)
            ->first();
    }

    /** Everyone with work on this board, for the filter. */
    #[Computed]
    public function owners(): Collection
    {
        return $this->scope()
            ->applyToTasks(Task::query())
            ->with('assignedTo')
            ->get()
            ->pluck('assignedTo')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /** Whoever is reading, under the office this page belongs to. */
    private function reader(): ?App\Models\User
    {
        return auth($this->asRole)->user();
    }

    /**
     * Move a task to another column.
     *
     * Reaching the last column finishes it, so the board and the tick are the
     * same act — otherwise a task sits in «منجزة» while every report still
     * counts it outstanding.
     */
    public function moveTo(int $id, string $stage): void
    {
        abort_unless(array_key_exists($stage, Task::STAGES), 422);

        $task = $this->scope()->applyToTasks(Task::query())->whereKey($id)->firstOrFail();
        $was = $task->stageLabel();

        $task->forceFill([
            'stage' => $stage,
            'status' => $stage === Task::FINISHED ? 'completed' : 'pending',
        ])->save();

        app(TaskFollowUpService::class)->record(
            $task, $this->reader()?->id, $this->asRole, TaskActivity::STAGE, $was, Task::STAGES[$stage],
        );

        unset($this->columns, $this->task);
    }

    public function addStep(): void
    {
        $task = $this->task;

        abort_unless($task, 404);

        $title = trim($this->newStep);

        if ($title === '') {
            return;
        }

        $task->steps()->create([
            'title' => $title,
            'sort_order' => (int) $task->steps()->max('sort_order') + 1,
        ]);

        $this->newStep = '';
        unset($this->task, $this->columns);
    }

    public function toggleStep(int $stepId): void
    {
        $task = $this->task;

        abort_unless($task, 404);

        $step = $task->steps()->whereKey($stepId)->firstOrFail();
        $step->update(['is_done' => ! $step->is_done]);

        unset($this->task, $this->columns);
    }

    public function removeStep(int $stepId): void
    {
        $this->task?->steps()->whereKey($stepId)->delete();

        unset($this->task, $this->columns);
    }

    public function comment(): void
    {
        $task = $this->task;

        abort_unless($task, 404);

        $body = trim($this->newComment);

        if ($body === '') {
            return;
        }

        $task->comments()->create([
            'author_type' => $this->asRole,
            'author_id' => $this->reader()?->id,
            'body' => $body,
        ]);

        $this->newComment = '';
        unset($this->task);

        Flux::toast(__('أُضيفت الملاحظة.'), variant: 'success');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <x-section-heading icon="rectangle-stack">
            {{ __('لوحة المهام') }}

            <x-slot:detail>
                {{ __('أين وقفت كلّ مهمّة، وماذا جرى لها منذ أُسندت.') }}
            </x-slot:detail>
        </x-section-heading>

        <flux:select wire:model.live="onlyFor" class="max-w-56">
            <flux:select.option value="">{{ __('الجميع') }}</flux:select.option>
            @foreach ($this->owners as $owner)
                <flux:select.option value="{{ $owner->id }}">{{ $owner->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- الأعمدة --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @foreach ($this->columns as $key => $tasks)
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-3 space-y-2"
                wire:key="col-{{ $key }}">
                <div class="flex items-center justify-between px-1 pb-2 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ Task::STAGES[$key] }}</span>
                    <flux:badge size="sm">{{ $tasks->count() }}</flux:badge>
                </div>

                @forelse ($tasks as $task)
                    @php
                        $progress = $task->stepProgress();
                        $late = $task->isOverdue();
                    @endphp
                    <button type="button" wire:click="$set('openTask', {{ $task->id }})"
                        wire:key="t-{{ $task->id }}"
                        class="w-full text-start rounded-xl border p-3 transition-colors
                            {{ $late ? 'border-rose-300 bg-rose-50/60 dark:bg-rose-950/20' : 'border-zinc-100 dark:border-zinc-800 hover:border-maroon/40' }}">
                        <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">{{ $task->title }}</div>

                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-zinc-500">
                            @if ($task->assignedTo)
                                <span>{{ $task->assignedTo->name }}</span>
                            @endif

                            @if ($task->due_date)
                                <span class="{{ $late ? 'text-rose-600 font-bold' : '' }}">
                                    · <x-hijri-date :date="$task->due_date" />
                                </span>
                            @endif

                            @if ($task->escalated_at)
                                <flux:badge size="sm" color="amber">{{ __('أُبلغ عنها') }}</flux:badge>
                            @endif
                        </div>

                        @if ($progress !== null)
                            <div class="mt-2 flex items-center gap-2">
                                <div class="h-1.5 flex-1 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                    <div class="h-full rounded-full bg-maroon" style="width: {{ $progress }}%"></div>
                                </div>
                                <span class="text-[10px] text-zinc-400 tabular-nums">{{ $progress }}%</span>
                            </div>
                        @endif
                    </button>
                @empty
                    <p class="px-1 py-3 text-xs text-zinc-400">{{ __('لا شيء هنا.') }}</p>
                @endforelse
            </div>
        @endforeach
    </div>

    {{-- تفصيل المهمة --}}
    @if ($this->task)
        @php $task = $this->task; @endphp
        <flux:card class="space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ $task->title }}</flux:heading>
                    @if ($task->description)
                        <flux:subheading class="mt-1">{{ $task->description }}</flux:subheading>
                    @endif
                </div>

                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="$set('openTask', null)">
                    {{ __('إغلاق') }}
                </flux:button>
            </div>

            {{-- نقلها بين الأعمدة --}}
            <div class="flex flex-wrap gap-1.5">
                @foreach (Task::STAGES as $key => $label)
                    <flux:button size="sm" :variant="$task->stage === $key ? 'primary' : 'ghost'"
                        wire:click="moveTo({{ $task->id }}, '{{ $key }}')">
                        {{ $label }}
                    </flux:button>
                @endforeach
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- الخطوات --}}
                <div class="space-y-2">
                    <div class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ __('الخطوات') }}</div>

                    @forelse ($task->steps as $step)
                        <div class="flex items-center gap-2 group" wire:key="s-{{ $step->id }}">
                            <button type="button" wire:click="toggleStep({{ $step->id }})"
                                class="shrink-0 size-4 rounded border-2 flex items-center justify-center
                                    {{ $step->is_done ? 'bg-emerald-500 border-emerald-500 text-white' : 'border-zinc-300 dark:border-zinc-600 text-transparent' }}">
                                <flux:icon icon="check" class="size-3" />
                            </button>

                            <span class="text-sm {{ $step->is_done ? 'line-through text-zinc-400' : 'text-zinc-700 dark:text-zinc-200' }}">
                                {{ $step->title }}
                            </span>

                            <button type="button" wire:click="removeStep({{ $step->id }})"
                                class="ms-auto opacity-0 group-hover:opacity-100 text-zinc-300 hover:text-rose-500">
                                <flux:icon icon="x-mark" class="size-3.5" />
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-zinc-400">{{ __('بلا خطوات — والمهمة لا تُقاس بنسبة حتى تُقسَّم.') }}</p>
                    @endforelse

                    <form wire:submit="addStep" class="flex gap-2 pt-1">
                        <flux:input size="sm" wire:model="newStep" :placeholder="__('خطوة جديدة')" />
                        <flux:button size="sm" type="submit" icon="plus" />
                    </form>
                </div>

                {{-- الملاحظات --}}
                <div class="space-y-2">
                    <div class="text-sm font-bold text-zinc-700 dark:text-zinc-200">{{ __('الملاحظات') }}</div>

                    <div class="space-y-2 max-h-52 overflow-y-auto">
                        @forelse ($task->comments as $comment)
                            <div class="rounded-xl bg-zinc-50 dark:bg-zinc-800/50 px-3 py-2" wire:key="c-{{ $comment->id }}">
                                <div class="text-[11px] text-zinc-500">{{ $comment->authorLine() }}</div>
                                <div class="text-sm text-zinc-700 dark:text-zinc-200 mt-0.5">{{ $comment->body }}</div>
                            </div>
                        @empty
                            <p class="text-xs text-zinc-400">{{ __('لا ملاحظات بعد.') }}</p>
                        @endforelse
                    </div>

                    <form wire:submit="comment" class="flex gap-2 pt-1">
                        <flux:input size="sm" wire:model="newComment" :placeholder="__('اكتب ملاحظة')" />
                        <flux:button size="sm" type="submit" icon="paper-airplane" />
                    </form>
                </div>
            </div>

            {{-- السجلّ --}}
            <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <div class="text-sm font-bold text-zinc-700 dark:text-zinc-200 mb-2">{{ __('ما جرى لها') }}</div>

                <div class="space-y-1.5 max-h-40 overflow-y-auto">
                    @foreach ($task->activities as $line)
                        <div class="flex items-baseline gap-2 text-xs" wire:key="a-{{ $line->id }}">
                            <span class="text-zinc-400 tabular-nums shrink-0" dir="ltr">
                                {{ $line->created_at->format('m-d H:i') }}
                            </span>
                            <span class="text-zinc-600 dark:text-zinc-300">{{ $line->say() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </flux:card>
    @endif
</div>
