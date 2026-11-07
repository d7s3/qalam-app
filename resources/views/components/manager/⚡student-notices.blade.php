<?php

use App\Models\Student;
use App\Models\StudentNotice;
use App\Support\Scope;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Write to chosen students by name, and, when wanted, hold their accounts shut
 * until each types a code he is given in person.
 */
new class extends Component
{
    public string $search = '';

    /** @var array<int, int> */
    public array $selected = [];

    public string $body = 'السلام عليكم يا '.StudentNotice::NAME.'، ';

    public string $unlockCode = '';

    /** Students the manager may write to: those within his reach. */
    private function students()
    {
        return Scope::forRole('manager')->applyToStudents(Student::query());
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function matches(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return collect();
        }

        return $this->students()
            ->where('name', 'like', '%'.trim($this->search).'%')
            ->whereNotIn('id', $this->selected)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);
    }

    /** @return Collection<int, Student> */
    #[Computed]
    public function chosen(): Collection
    {
        return Student::whereIn('id', $this->selected)->orderBy('name')->get(['id', 'name']);
    }

    /** @return Collection<int, StudentNotice> */
    #[Computed]
    public function sent(): Collection
    {
        return StudentNotice::with('student:id,name')
            ->whereIn('student_id', $this->students()->select('id'))
            ->latest('id')
            ->limit(50)
            ->get();
    }

    public function choose(int $studentId): void
    {
        abort_unless($this->students()->whereKey($studentId)->exists(), 404);

        $this->selected[] = $studentId;
        $this->selected = array_values(array_unique($this->selected));
        $this->search = '';
    }

    public function drop(int $studentId): void
    {
        $this->selected = array_values(array_diff($this->selected, [$studentId]));
    }

    public function send(): void
    {
        $this->validate([
            'selected' => ['required', 'array', 'min:1'],
            'body' => ['required', 'string', 'max:2000'],
            'unlockCode' => ['nullable', 'string', 'max:50'],
        ], [], ['selected' => 'الطلاب', 'body' => 'الرسالة', 'unlockCode' => 'الرمز']);

        $ids = $this->students()->whereIn('id', $this->selected)->pluck('id');
        $manager = Auth::guard('manager')->user();

        DB::transaction(fn () => $ids->each(fn (int $id) => StudentNotice::create([
            'student_id' => $id,
            'body' => trim($this->body),
            'unlock_code' => trim($this->unlockCode) ?: null,
            'sent_by_type' => $manager?->getMorphClass(),
            'sent_by_id' => $manager?->getKey(),
        ])));

        Flux::toast(__('أُرسلت الرسالة إلى :count طالب.', ['count' => $ids->count()]), variant: 'success');

        $this->reset('selected', 'unlockCode', 'search');
        $this->body = 'السلام عليكم يا '.StudentNotice::NAME.'، ';
        unset($this->sent);
    }

    /** Take a message back before it is opened, which also opens the account it held. */
    public function withdraw(int $noticeId): void
    {
        StudentNotice::waiting()->whereIn('student_id', $this->students()->select('id'))->whereKey($noticeId)->delete();
        unset($this->sent);

        Flux::toast(__('سُحبت الرسالة.'), variant: 'success');
    }
};
?>

<div class="space-y-6 p-4 sm:p-6 lg:p-8" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold">{{ __('رسائل خاصة للطلاب') }}</flux:heading>
        <flux:subheading class="mt-1">
            {{ __('اكتب لطلاب تختارهم بأسمائهم، فتظهر الرسالة لكل واحد منهم أول ما يدخل. وإن وضعت رمزاً، بقي حسابه مغلقاً حتى يُدخله.') }}
        </flux:subheading>
    </div>

    <flux:card class="space-y-5">
        <flux:field>
            <flux:label>{{ __('الطلاب') }}</flux:label>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('اكتب اسم الطالب') }}" />
            <flux:error name="selected" />

            @if ($this->matches->isNotEmpty())
                <div class="mt-1 rounded-xl border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->matches as $match)
                        <button type="button" wire:key="m-{{ $match->id }}" wire:click="choose({{ $match->id }})"
                            class="w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            {{ $match->name }}
                        </button>
                    @endforeach
                </div>
            @endif
        </flux:field>

        @if ($this->chosen->isNotEmpty())
            <div class="flex flex-wrap gap-2" data-chosen>
                @foreach ($this->chosen as $student)
                    <span wire:key="c-{{ $student->id }}" class="inline-flex items-center gap-1.5 rounded-full bg-maroon/10 px-3 py-1 text-sm text-maroon dark:bg-white/10 dark:text-white">
                        {{ $student->name }}
                        <button type="button" wire:click="drop({{ $student->id }})" aria-label="{{ __('إزالة') }}">
                            <flux:icon icon="x-mark" class="size-3.5" />
                        </button>
                    </span>
                @endforeach
            </div>
        @endif

        <flux:field>
            <flux:label>{{ __('الرسالة') }}</flux:label>
            <flux:textarea wire:model="body" rows="5" />
            <flux:description>{{ __('اكتب :name حيث تريد أن يظهر اسم الطالب.', ['name' => StudentNotice::NAME]) }}</flux:description>
            <flux:error name="body" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('رمز فتح الحساب (اختياري)') }}</flux:label>
            <flux:input wire:model="unlockCode" dir="ltr" class="sm:w-60" placeholder="{{ __('مثلاً 1448') }}" />
            <flux:description>{{ __('إن كتبته، أُغلق حساب الطالب حتى يُدخل هذا الرمز. وإن تركته فارغاً، يقرأ الرسالة ويتابع.') }}</flux:description>
            <flux:error name="unlockCode" />
        </flux:field>

        <flux:button variant="primary" icon="paper-airplane" wire:click="send">{{ __('أرسل') }}</flux:button>
    </flux:card>

    @if ($this->sent->isNotEmpty())
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('ما أرسلته') }}</flux:heading>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->sent as $notice)
                    <div wire:key="n-{{ $notice->id }}" class="py-3 flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="font-bold text-sm text-zinc-900 dark:text-white">{{ $notice->student?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400 truncate">{{ $notice->bodyFor($notice->student?->name ?? '') }}</div>
                        </div>
                        <div class="flex items-center gap-2 text-xs shrink-0">
                            @if ($notice->locks())
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 dark:bg-zinc-800" dir="ltr">{{ __('الرمز') }}: {{ $notice->unlock_code }}</span>
                            @endif
                            @if ($notice->opened_at)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">{{ __('فتحها') }} <x-hijri-date :date="$notice->opened_at" /></span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ __('لم يفتحها بعد') }}</span>
                                <flux:button size="xs" variant="ghost" wire:click="withdraw({{ $notice->id }})" wire:confirm="{{ __('تسحب الرسالة؟ وإن كان حسابه مغلقاً بها فُتح.') }}">{{ __('اسحبها') }}</flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif
</div>
