<?php

use App\Models\Motivation;
use App\Support\RoleHierarchy;
use App\Support\Scope;
use Livewire\Component;

/**
 * The store of what is worth meeting on opening.
 *
 * Anyone may contribute — a teacher, a supervisor, a student. Nothing is shown
 * until somebody senior says it may be, because the academy's condition is that
 * only the authentic and the good are put in front of a student and no program
 * can judge that. What this does is refuse to draw the unreviewed, and keep the
 * attribution and the grading as fields rather than as a promise.
 */
new class extends Component
{
    public string $asRole = 'student';

    public string $kind = 'athar';

    public string $text = '';

    public string $source = '';

    public string $grade = '';

    public string $filter = 'approved';

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
        $this->filter = $this->mayReview() ? 'pending' : 'approved';
    }

    private function reader(): ?\App\Models\User
    {
        return Scope::forRole($this->asRole)->user();
    }

    /** An office that carries another is an office that may pass what it holds. */
    public function mayReview(): bool
    {
        return RoleHierarchy::inheritedBy($this->asRole) !== [];
    }

    public function contribute(): void
    {
        $this->validate([
            'kind' => ['required', 'in:ayah,hadith,athar,poetry'],
            'text' => ['required', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:60'],
        ], [], ['text' => __('النص'), 'kind' => __('النوع')]);

        // A hadith without a grading the academy accepts is not refused here —
        // it is simply never drawn. The reviewer sees why.
        Motivation::create([
            'kind' => $this->kind,
            'text' => $this->text,
            'source' => $this->source ?: null,
            'grade' => $this->grade ?: null,
            'contributed_by' => $this->reader()?->id,
            'contributor_role' => $this->asRole,
            'status' => Motivation::PENDING,
        ]);

        $this->reset(['text', 'source', 'grade']);

        Flux::toast(__('وصلت، وتنتظر المراجعة'), variant: 'success');
    }

    public function approve(int $id): void
    {
        abort_unless($this->mayReview(), 403);

        $one = Motivation::findOrFail($id);

        abort_unless($one->gradeIsAcceptable(), 422, __('لا يُعتمد حديث إلا بدرجة صحيح أو حسن.'));

        $one->update([
            'status' => Motivation::APPROVED,
            'reviewed_by' => $this->reader()?->id,
            'reviewed_at' => now(),
        ]);

        Flux::toast(__('اعتُمد'), variant: 'success');
    }

    public function reject(int $id, string $note = ''): void
    {
        abort_unless($this->mayReview(), 403);

        Motivation::findOrFail($id)->update([
            'status' => Motivation::REJECTED,
            'reviewed_by' => $this->reader()?->id,
            'reviewed_at' => now(),
            'review_note' => $note ?: null,
        ]);

        Flux::toast(__('لم يُعتمد'), variant: 'success');
    }

    public function with(): array
    {
        $user = $this->reader();

        $query = Motivation::with(['contributor', 'reviewer'])->latest();

        // Somebody who cannot review sees what is shown, and his own waiting.
        if (! $this->mayReview()) {
            $query->where(fn ($q) => $q->where('status', Motivation::APPROVED)
                ->orWhere('contributed_by', $user?->id));
        } else {
            $query->where('status', $this->filter);
        }

        return ['items' => $query->take(60)->get()];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ __('مستودع الشواهد') }}</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('آية أو حديث أو أثر أو بيت، يظهر للطلاب أول ما يفتحون التطبيق. ولا يُعرض شيء حتى يُعتمد.') }}
        </flux:subheading>
    </div>

    <flux:card class="space-y-4">
        <flux:heading size="sm">{{ __('أضف شاهداً') }}</flux:heading>

        <div class="grid gap-3 md:grid-cols-4">
            <flux:field>
                <flux:label>{{ __('النوع') }}</flux:label>
                <flux:select wire:model.live="kind">
                    <flux:select.option value="ayah">{{ __('آية') }}</flux:select.option>
                    <flux:select.option value="hadith">{{ __('حديث') }}</flux:select.option>
                    <flux:select.option value="athar">{{ __('أثر') }}</flux:select.option>
                    <flux:select.option value="poetry">{{ __('بيت') }}</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>{{ __('التخريج') }}</flux:label>
                <flux:input wire:model="source" placeholder="{{ __('السورة والآية، أو الكتاب ورقمه، أو من قاله') }}" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('الدرجة') }}</flux:label>
                <flux:input wire:model="grade" placeholder="{{ __('صحيح / حسن') }}"
                    :disabled="$kind !== 'hadith'" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('النص') }}</flux:label>
            <flux:textarea wire:model="text" rows="3" />
            <flux:error name="text" />
        </flux:field>

        @if($kind === 'hadith')
            <flux:text class="text-xs text-amber-700 dark:text-amber-400">
                {{ __('لا يُعتمد الحديث إلا بدرجة صحيح أو حسن، ولا يُعرض قبل الاعتماد.') }}
            </flux:text>
        @endif

        <flux:button variant="primary" class="!bg-maroon hover:!bg-burgundy" wire:click="contribute">
            {{ __('أضف') }}
        </flux:button>
    </flux:card>

    @if($this->mayReview())
        <div class="flex items-center gap-2">
            @foreach(['pending' => __('بانتظار المراجعة'), 'approved' => __('المعتمد'), 'rejected' => __('المردود')] as $value => $label)
                <button wire:click="$set('filter', '{{ $value }}')" wire:key="f-{{ $value }}"
                    class="px-4 py-2 text-sm font-bold rounded-lg border transition-colors
                        {{ $filter === $value ? 'bg-maroon text-white border-maroon' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    @endif

    <div class="space-y-2">
        @forelse($items as $item)
            <flux:card wire:key="m-{{ $item->id }}" class="space-y-2">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 space-y-1">
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" color="zinc">{{ $item->kindLabel() }}</flux:badge>
                            @if($item->status === 'approved')
                                <flux:badge size="sm" color="lime">{{ __('معتمد') }}</flux:badge>
                            @elseif($item->status === 'rejected')
                                <flux:badge size="sm" color="rose">{{ __('مردود') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="amber">{{ __('بانتظار المراجعة') }}</flux:badge>
                            @endif
                            @if($item->kind === 'hadith' && ! $item->gradeIsAcceptable())
                                <flux:badge size="sm" color="rose">{{ __('بلا درجة معتبرة') }}</flux:badge>
                            @endif
                        </div>

                        <div class="text-sm leading-relaxed text-zinc-800 dark:text-zinc-100">{{ $item->text }}</div>

                        <div class="text-xs text-zinc-400">
                            {{ $item->source }}
                            @if($item->grade) · {{ $item->grade }} @endif
                            @if($item->contributor) · {{ $item->contributor->name }} @endif
                        </div>
                    </div>

                    @if($this->mayReview() && $item->status !== 'approved')
                        <div class="flex items-center gap-1 shrink-0">
                            <flux:button size="sm" variant="primary" class="!bg-emerald-600 hover:!bg-emerald-700"
                                wire:click="approve({{ $item->id }})">{{ __('اعتمد') }}</flux:button>
                            <flux:button size="sm" variant="ghost" class="text-rose-500"
                                wire:click="reject({{ $item->id }})">{{ __('ردّ') }}</flux:button>
                        </div>
                    @endif
                </div>
            </flux:card>
        @empty
            <flux:card><flux:text class="text-zinc-400">{{ __('لا شيء هنا بعد.') }}</flux:text></flux:card>
        @endforelse
    </div>
</div>
