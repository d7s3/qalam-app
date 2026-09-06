<?php

use App\Models\Role;
use App\Models\Student;
use App\Models\StudentNote;
use App\Services\StudentLogService;
use App\Support\Scope;
use Livewire\Component;

/**
 * A student's record of upbringing.
 *
 * Opened by his teacher, his supervisor or the manager, each of whom sees his
 * own notes and only those the others opened to him. Seniority does not open a
 * note: a supervisor who can read everything by his office reads a diary rather
 * than a record, and the teacher stops writing honestly.
 */
new class extends Component
{
    public string $asRole = 'teacher';

    public ?int $studentId = null;

    public string $search = '';

    public string $body = '';

    public string $notedOn = '';

    public string $visibility = 'private';

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
        $this->notedOn = now('Asia/Riyadh')->format('Y-m-d');
    }

    private function reader(): ?\App\Models\User
    {
        return Scope::forRole($this->asRole)->user();
    }

    /** Only the students his reach already gave him. */
    private function reachableStudents()
    {
        return Scope::forRole($this->asRole)
            ->applyToStudents(Student::query())
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->take(40)
            ->get();
    }

    private function student(): ?Student
    {
        return $this->studentId
            ? $this->reachableStudents()->firstWhere('id', $this->studentId)
            : null;
    }

    public function open(int $studentId): void
    {
        $this->studentId = $this->reachableStudents()->firstWhere('id', $studentId)?->id;
    }

    public function save(): void
    {
        $this->validate([
            'body' => ['required', 'string', 'max:4000'],
            'notedOn' => ['required', 'date'],
        ], [], ['body' => __('الملاحظة'), 'notedOn' => __('التاريخ')]);

        $student = $this->student() ?? abort(404);

        StudentLogService::write(
            $student,
            $this->reader() ?? abort(403),
            $this->asRole,
            $this->body,
            $this->notedOn,
            $this->visibility,
        );

        $this->reset(['body']);
        $this->notedOn = now('Asia/Riyadh')->format('Y-m-d');

        Flux::toast(__('حُفظت الملاحظة'), variant: 'success');
    }

    /** Open or close one of his own notes to an office. */
    public function toggleShare(int $noteId, string $roleKey): void
    {
        $author = $this->reader() ?? abort(403);
        $note = StudentNote::with('shares')->findOrFail($noteId);

        abort_unless($note->author_id === $author->id, 403);

        $held = $note->shares->contains(fn ($share) => $share->role_key === $roleKey);

        $held
            ? StudentLogService::closeTo($note, $author, $roleKey)
            : StudentLogService::openTo($note, $author, $roleKey);

        Flux::toast($held ? __('أُغلقت') : __('فُتحت'), variant: 'success');
    }

    /** Open one note to everyone who may see the student, or take that back. */
    public function toggleGeneral(int $noteId): void
    {
        $author = $this->reader() ?? abort(403);
        $note = StudentNote::findOrFail($noteId);

        abort_unless($note->author_id === $author->id, 403);

        StudentLogService::setVisibility(
            $note,
            $author,
            $note->isShared() ? StudentNote::PRIVATE : StudentNote::SHARED,
        );

        Flux::toast(__('غُيّرت الرؤية'), variant: 'success');
    }

    public function with(): array
    {
        $student = $this->student();
        $reader = $this->reader();

        return [
            'students' => $this->reachableStudents(),
            'student' => $student,
            'reader' => $reader,
            'notes' => $student && $reader
                ? StudentLogService::visibleTo($student, $reader, $this->asRole)
                : collect(),
            'offices' => Role::whereIn('key', ['manager', 'supervisor', 'teacher'])
                ->where('key', '!=', $this->asRole)
                ->pluck('label', 'key'),
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ __('السجل التربوي') }}</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('ما لاحظتَه على الطالب في يومه. ملاحظتك لك، ولا يقرؤها غيرك إلا بفتحك أنت.') }}
        </flux:subheading>
    </div>

    <flux:card class="space-y-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="{{ __('ابحث باسم الطالب...') }}" />

        <div class="flex items-center gap-2 flex-wrap">
            @forelse($students as $one)
                <button wire:click="open({{ $one->id }})" wire:key="s-{{ $one->id }}"
                    class="px-3 py-1.5 text-sm font-bold rounded-lg border transition-colors
                        {{ $student?->id === $one->id ? 'bg-maroon text-white border-maroon' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
                    {{ $one->name }}
                </button>
            @empty
                <flux:text class="text-zinc-400">{{ __('لا طلاب في مداك.') }}</flux:text>
            @endforelse
        </div>
    </flux:card>

    @if($student)
        <flux:card class="space-y-4">
            <flux:heading size="sm">{{ __('ملاحظة على') }} {{ $student->name }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('الملاحظة') }}</flux:label>
                <flux:textarea wire:model="body" rows="3"
                    placeholder="{{ __('ما رأيتَه، بلغتك أنت.') }}" />
                <flux:error name="body" />
            </flux:field>

            <div class="grid gap-3 md:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('اليوم') }}</flux:label>
                    <flux:input type="date" wire:model="notedOn" />
                    <flux:error name="notedOn" />
                </flux:field>

                <flux:field class="md:col-span-2">
                    <flux:label>{{ __('من يقرؤها') }}</flux:label>
                    <flux:select wire:model="visibility">
                        <flux:select.option value="private">{{ __('أنا وحدي، حتى أفتحها') }}</flux:select.option>
                        <flux:select.option value="shared">{{ __('كل من يرى الطالب') }}</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            <flux:button variant="primary" class="!bg-maroon hover:!bg-burgundy" wire:click="save">
                {{ __('احفظ') }}
            </flux:button>
        </flux:card>

        <div class="space-y-2">
            @forelse($notes as $note)
                @php
                    $mine = $note->author_id === $reader?->id;
                @endphp
                <flux:card wire:key="n-{{ $note->id }}" class="space-y-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="text-sm text-zinc-800 dark:text-zinc-100 whitespace-pre-line">{{ $note->body }}</div>
                            <div class="text-xs text-zinc-400 mt-1 flex items-center gap-2 flex-wrap">
                                <x-hijri-date :date="$note->noted_on" />
                                <span>· {{ $note->author?->name }}</span>
                                @if($note->isShared())
                                    <flux:badge size="sm" color="lime">{{ __('مفتوحة للجميع') }}</flux:badge>
                                @elseif($mine)
                                    <flux:badge size="sm" color="zinc">{{ __('خاصة') }}</flux:badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($mine)
                        <div class="flex items-center gap-1 flex-wrap pt-2 border-t border-zinc-50 dark:border-zinc-800/60">
                            <span class="text-xs text-zinc-400 ml-2">{{ __('افتحها لـ') }}</span>
                            @foreach($offices as $key => $label)
                                @php
                                    $open = $note->shares->contains(fn ($share) => $share->role_key === $key);
                                @endphp
                                <button wire:click="toggleShare({{ $note->id }}, '{{ $key }}')"
                                    wire:key="sh-{{ $note->id }}-{{ $key }}"
                                    class="px-2.5 py-1 text-xs font-bold rounded-lg border transition-colors
                                        {{ $open ? 'bg-emerald-600 text-white border-emerald-600' : 'border-zinc-200 dark:border-zinc-700 text-zinc-500' }}">
                                    {{ $label }}
                                </button>
                            @endforeach

                            <button wire:click="toggleGeneral({{ $note->id }})"
                                class="px-2.5 py-1 text-xs font-bold rounded-lg border transition-colors
                                    {{ $note->isShared() ? 'bg-maroon text-white border-maroon' : 'border-zinc-200 dark:border-zinc-700 text-zinc-500' }}">
                                {{ __('للجميع') }}
                            </button>
                        </div>
                    @endif
                </flux:card>
            @empty
                <flux:card><flux:text class="text-zinc-400">{{ __('لا ملاحظات تراها على هذا الطالب.') }}</flux:text></flux:card>
            @endforelse
        </div>
    @endif
</div>
