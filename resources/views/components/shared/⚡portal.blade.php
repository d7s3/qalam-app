<?php

use App\Models\PortalMessage;
use App\Models\Role;
use App\Services\PortalService;
use App\Support\Scope;
use Livewire\Component;

/**
 * Announcing a word to one's own office and those beneath it.
 *
 * Never upward: an announcement is not how a teacher reaches the manager, and
 * the application already has a conversation for that.
 */
new class extends Component
{
    public string $asRole = 'manager';

    public string $title = '';

    public string $body = '';

    public array $toRoles = [];

    public bool $showSender = true;

    public string $startsOn = '';

    public string $endsOn = '';

    public function mount(): void
    {
        $this->asRole = Scope::resolveRole();
    }

    private function sender(): ?\App\Models\User
    {
        return Scope::forRole($this->asRole)->user();
    }

    public function send(): void
    {
        $this->validate([
            'body' => ['required', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:120'],
            'toRoles' => ['required', 'array', 'min:1'],
            'endsOn' => ['nullable', 'date', 'after_or_equal:startsOn'],
        ], [], ['body' => __('نصّ الرسالة'), 'toRoles' => __('المرسَل إليهم')]);

        $sender = $this->sender() ?? abort(403);

        $message = PortalService::announce(
            $sender,
            $this->asRole,
            $this->body,
            array_values($this->toRoles),
            [],
            $this->title ?: null,
            $this->showSender,
            $this->startsOn ?: null,
            $this->endsOn ?: null,
        );

        if (! $message) {
            Flux::toast(__('لا تملك مخاطبة من اخترت'), variant: 'danger');

            return;
        }

        $this->reset(['title', 'body', 'toRoles', 'startsOn', 'endsOn']);

        Flux::toast(__('أُرسلت'), variant: 'success');
    }

    public function withdraw(int $id): void
    {
        PortalMessage::where('sender_id', $this->sender()?->id)->findOrFail($id)->delete();

        Flux::toast(__('سُحبت'), variant: 'success');
    }

    public function with(): array
    {
        return [
            'targets' => Role::whereIn('key', PortalService::canAddress($this->asRole))->pluck('label', 'key'),
            'sent' => PortalMessage::where('sender_id', $this->sender()?->id)
                ->withCount('reads')
                ->latest()
                ->take(20)
                ->get(),
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">{{ __('بوابة الرسائل') }}</flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('كلمة تصل من تخاطبهم أول ما يفتحون التطبيق. تخاطب من هم في منصبك ومن دونه.') }}
        </flux:subheading>
    </div>

    <flux:card class="space-y-4">
        <flux:field>
            <flux:label>{{ __('العنوان') }}</flux:label>
            <flux:input wire:model="title" placeholder="{{ __('اختياري') }}" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('نصّ الرسالة') }}</flux:label>
            <flux:textarea wire:model="body" rows="4" />
            <flux:error name="body" />
        </flux:field>

        <div>
            <flux:label class="mb-2 block">{{ __('إلى') }}</flux:label>
            <div class="flex items-center gap-2 flex-wrap">
                @foreach($targets as $key => $label)
                    <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 cursor-pointer"
                        wire:key="to-{{ $key }}">
                        <input type="checkbox" value="{{ $key }}" wire:model="toRoles" class="accent-maroon" />
                        <span class="text-sm">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <flux:error name="toRoles" />
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <flux:field>
                <flux:label>{{ __('من تاريخ') }}</flux:label>
                <flux:input type="date" wire:model="startsOn" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('إلى تاريخ') }}</flux:label>
                <flux:input type="date" wire:model="endsOn" />
                <flux:error name="endsOn" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('اسمي') }}</flux:label>
                <flux:select wire:model="showSender">
                    <flux:select.option value="1">{{ __('يظهر') }}</flux:select.option>
                    <flux:select.option value="0">{{ __('لا يظهر') }}</flux:select.option>
                </flux:select>
            </flux:field>
        </div>

        <flux:button variant="primary" class="!bg-maroon hover:!bg-burgundy" wire:click="send">
            {{ __('أرسل') }}
        </flux:button>
    </flux:card>

    <div class="space-y-2">
        <flux:heading size="lg">{{ __('ما أرسلتَه') }}</flux:heading>

        @forelse($sent as $message)
            <flux:card wire:key="sent-{{ $message->id }}" class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-sm font-bold text-zinc-800 dark:text-zinc-100">
                        {{ $message->title ?: Str::limit($message->body, 60) }}
                    </div>
                    <div class="text-xs text-zinc-400 mt-1 flex items-center gap-2 flex-wrap">
                        <x-hijri-date :date="$message->created_at" />
                        <span>· {{ trans_choice('{0} لم يقرأها أحد|{1} قرأها واحد|[2,*] قرأها :count', $message->reads_count, ['count' => $message->reads_count]) }}</span>
                        @unless($message->show_sender)
                            <flux:badge size="sm" color="zinc">{{ __('بلا اسم') }}</flux:badge>
                        @endunless
                    </div>
                </div>
                <flux:button size="sm" variant="ghost" icon="trash" class="text-rose-500 shrink-0"
                    wire:confirm="{{ __('سحب الرسالة؟') }}" wire:click="withdraw({{ $message->id }})" />
            </flux:card>
        @empty
            <flux:card><flux:text class="text-zinc-400">{{ __('لم ترسل شيئاً بعد.') }}</flux:text></flux:card>
        @endforelse
    </div>
</div>
