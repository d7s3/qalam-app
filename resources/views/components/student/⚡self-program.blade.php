<?php

use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Services\SelfProgramService;
use App\Models\SelfProgramTrack;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component
{
    /** The amount the student is entering against each track, keyed by item id. */
    public array $amounts = [];

    /** The day being recorded against — today unless the student picks another. */
    public string $day = '';

    /** What the student is settling against each overdue track, keyed by item id. */
    public array $settleAmounts = [];

    public function mount(): void
    {
        $this->day = Carbon::today()->toDateString();
        $this->loadAmounts();
    }

    public function updatedDay(): void
    {
        $this->loadAmounts();
    }

    /**
     * Put what is already recorded for the chosen day into the inputs, so the
     * student edits his day rather than adding to it blindly.
     */
    private function loadAmounts(): void
    {
        $service = app(SelfProgramService::class);
        $student = Auth::guard('student')->user();

        foreach ($this->openItems() as $item) {
            $done = $service->doneByDay($item, $student);
            $this->amounts[$item->id] = $done[$this->day] ?? null;
        }
    }

    /**
     * Held for the request: several of the methods below want the same week,
     * and each lookup is a query.
     */
    private ?SelfProgramWeek $loadedWeek = null;

    private bool $weekResolved = false;

    private ?SelfProgramWeek $loadedEnrichment = null;

    private bool $enrichmentResolved = false;

    private function week(): ?SelfProgramWeek
    {
        if (! $this->weekResolved) {
            $this->loadedWeek = app(SelfProgramService::class)->currentWeek(Auth::guard('student')->user());
            $this->weekResolved = true;
        }

        return $this->loadedWeek;
    }

    private function enrichmentWeek(): ?SelfProgramWeek
    {
        if (! $this->enrichmentResolved) {
            $this->loadedEnrichment = app(SelfProgramService::class)
                ->currentEnrichmentWeek(Auth::guard('student')->user());
            $this->enrichmentResolved = true;
        }

        return $this->loadedEnrichment;
    }

    /**
     * Whether this track is written by the student's teacher rather than by
     * him.
     *
     * In a circle that memorises, the Quran wird comes from the recitation the
     * teacher grades. Leaving the field editable meant the page showed him that
     * figure and then added it a second time under his own name when he pressed
     * confirm — two pages of reading recorded as four.
     */
    private function bridged(SelfProgramItem $item): bool
    {
        return $item->track?->isQuranWird()
            && (bool) Auth::guard('student')->user()->circle?->is_quranic;
    }

    /**
     * The tracks the student may record against: his own week's, and the
     * enrichment week's once it has opened for him.
     *
     * @return Collection<int, SelfProgramItem>
     */
    private function openItems(): Collection
    {
        return collect([$this->week(), $this->enrichmentWeek()])
            ->filter()
            ->flatMap(fn ($week) => $week->items);
    }

    /**
     * Record what the student says he did on the chosen day.
     */
    public function save(int $itemId): void
    {
        $item = $this->openItems()->firstWhere('id', $itemId);

        // The item must belong to a week this student actually has open.
        abort_unless($item instanceof SelfProgramItem, 404);

        // The wird of a memorising circle is the teacher's to record.
        abort_if($this->bridged($item), 403);

        $this->validate([
            "amounts.{$itemId}" => ['nullable', 'numeric', 'min:0', 'max:9999'],
        ], [], ["amounts.{$itemId}" => 'المقدار']);

        app(SelfProgramService::class)->record(
            Auth::guard('student')->user(),
            $item,
            (float) ($this->amounts[$itemId] ?? 0),
            Carbon::parse($this->day),
        );
    }

    /**
     * Put work against a track of a week that has already ended.
     *
     * Recorded under today's date rather than the old week's, because that is
     * when it was done — and it still counts towards the week it belonged to,
     * which is the week the arrear is measured against.
     */
    public function settle(int $itemId): void
    {
        $student = Auth::guard('student')->user();
        $service = app(SelfProgramService::class);

        $arrear = collect($service->arrears($student))->firstWhere('item.id', $itemId);

        abort_unless($arrear !== null, 404);

        $this->validate([
            "settleAmounts.{$itemId}" => ['required', 'numeric', 'min:0.01', 'max:9999'],
        ], [], ["settleAmounts.{$itemId}" => 'المقدار']);

        $item = $arrear['item'];
        $today = Carbon::today();

        // Only what he put down himself today: folding in a figure written by a
        // recitation would store it again under his own name.
        $already = $service->recordedBy($student, $item, $today);

        $service->record($student, $item, $already + (float) $this->settleAmounts[$itemId], $today);

        $this->settleAmounts[$itemId] = null;
    }

    public function with(): array
    {
        $student = Auth::guard('student')->user();
        $service = app(SelfProgramService::class);
        $week = $this->week();
        $arrears = $service->arrears($student);

        if (! $week) {
            return [
                'week' => null, 'tracks' => [], 'overall' => 0, 'days' => [], 'plans' => [],
                'unlocked' => false, 'arrears' => $arrears, 'bridged' => [],
                'enrichment' => null, 'enrichmentProgress' => null,
            ];
        }

        $progress = $service->weekProgress($student, $week);
        $enrichment = $this->enrichmentWeek();

        $plans = [];
        $bridged = [];

        foreach ($week->items as $item) {
            $plans[$item->id] = $service->dailyPlan($item, $student);
            $bridged[$item->id] = $this->bridged($item);
        }

        return [
            'week' => $week,
            'tracks' => $progress['tracks'],
            'overall' => $progress['overall'],
            'days' => $service->workingDays($week),
            'plans' => $plans,
            'bridged' => $bridged,
            // The reading is already in hand, so the threshold is read off it
            // rather than recomputed.
            'unlocked' => $service->enrichmentUnlocked($student, $week, $progress['overall']),
            'arrears' => $arrears,
            'enrichment' => $enrichment,
            'enrichmentProgress' => $enrichment ? $service->weekProgress($student, $enrichment) : null,
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold text-zinc-900 dark:text-white">
            {{ __('البرنامج الذاتي') }}
        </flux:heading>
        <flux:subheading class="text-zinc-500 dark:text-zinc-400 mt-1">
            {{ __('أنجز ما شئت من محتوى أسبوعك متى شئت — والتقسيم اليومي اقتراح يتكيّف معك.') }}
        </flux:subheading>
    </div>


    {{-- متأخرات الأسابيع الماضية --}}
    @if (! empty($arrears))
        <flux:card class="border-amber-200 dark:border-amber-900/50">
            <div class="flex items-center gap-2.5 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="p-2 rounded-xl bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400">
                    <flux:icon icon="clock" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">{{ __('المتأخرات') }}</flux:heading>
                    <flux:subheading class="mt-0.5">
                        {{ __('ما بقي عليك من أسابيع مضت — يُحتسب لأسبوعه لا لأسبوعك الحالي.') }}
                    </flux:subheading>
                </div>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($arrears as $arrear)
                    <div wire:key="arrear-{{ $arrear['item']->id }}" class="py-3 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <div class="text-sm font-bold text-zinc-900 dark:text-white">
                                {{ $arrear['item']->track->label() }}
                                <span class="text-xs font-medium text-zinc-400">
                                    — {{ __('الأسبوع') }} {{ $arrear['week']->week_number }}
                                </span>
                            </div>
                            <div class="text-xs text-amber-600 dark:text-amber-400 tabular-nums mt-0.5">
                                {{ __('بقي') }}
                                {{ rtrim(rtrim(number_format($arrear['remaining'], 2), '0'), '.') }}
                                {{ $arrear['item']->displayUnit() }}
                                <span class="text-zinc-400">
                                    ({{ rtrim(rtrim(number_format($arrear['done'], 2), '0'), '.') }}
                                    {{ __('من') }}
                                    {{ rtrim(rtrim(number_format($arrear['target'], 2), '0'), '.') }})
                                </span>
                            </div>
                        </div>
                        <div class="flex items-end gap-2">
                            <flux:field>
                                <flux:input type="number" step="0.25" min="0" class="w-32"
                                    wire:model="settleAmounts.{{ $arrear['item']->id }}"
                                    placeholder="{{ __('ما أنجزته') }}" />
                                <flux:error name="settleAmounts.{{ $arrear['item']->id }}" />
                            </flux:field>
                            <flux:button variant="filled" wire:click="settle({{ $arrear['item']->id }})" class="mb-0.5">
                                {{ __('أضف') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>
    @endif

    @if (! $week)
        <flux:card class="text-center py-12">
            <flux:icon icon="calendar" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600" />
            <flux:heading size="lg" class="mt-3">{{ __('لا يوجد أسبوع مفتوح') }}</flux:heading>
            <flux:subheading class="mt-1">
                {{ __('لم يضع مشرف برنامجك محتوى لهذا الأسبوع بعد.') }}
            </flux:subheading>
        </flux:card>
    @else
        {{-- تقدّم الأسبوع كاملاً --}}
        <flux:card>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('الأسبوع') }} {{ $week->week_number }}
                    </div>
                    <div class="text-sm font-medium text-zinc-700 dark:text-zinc-200 mt-0.5">
                        <x-hijri-date :date="$week->starts_on" /> — <x-hijri-date :date="$week->ends_on" />
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-3xl font-black text-zinc-900 dark:text-white tabular-nums">{{ $overall }}%</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('متوسط المجالات') }}</div>
                </div>
            </div>

            <div class="mt-4 h-2.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ max($overall, 1) }}%"></div>
            </div>

            <div class="mt-4 flex items-center gap-2 text-xs {{ $unlocked ? 'text-emerald-600 dark:text-emerald-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                <flux:icon :icon="$unlocked ? 'lock-open' : 'lock-closed'" class="size-4" />
                @if ($unlocked)
                    {{ __('البرنامج الإثرائي مفتوح لك هذا الأسبوع.') }}
                @else
                    {{ __('أكمل ٥٠٪ من متوسط مجالاتك ليُفتح لك البرنامج الإثرائي.') }}
                @endif
            </div>
        </flux:card>

        {{-- اليوم الذي يسجّل عليه --}}
        <flux:card>
            <flux:field>
                <flux:label>{{ __('سجّل إنجاز يوم') }}</flux:label>
                <flux:select wire:model.live="day">
                    @foreach ($days as $date)
                        <flux:select.option value="{{ $date }}">
                            {{ \App\Support\HijriDate::weekday($date) }} — {{ \App\Support\HijriDate::withGregorian($date) }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>
        </flux:card>

        {{-- المجالات الخمسة --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            @foreach ($tracks as $entry)
                @php
                    $item = $entry['item'];
                    $suggested = $plans[$item->id][$day] ?? 0;
                @endphp
                <flux:card wire:key="track-{{ $item->id }}" class="flex flex-col gap-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <div class="p-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                                <flux:icon :icon="$item->track->icon()" class="size-5" />
                            </div>
                            <div>
                                <div class="font-bold text-zinc-900 dark:text-white">{{ $item->track->label() }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $item->description ?: __('لم يُحدَّد محتوى') }}
                                    @if ($item->content_url)
                                        <a href="{{ $item->content_url }}" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 mt-1 text-xs font-bold text-maroon dark:text-red-secondary hover:underline">
                                            <flux:icon name="arrow-top-right-on-square" class="size-3" />
                                            {{ $item->contentLinkLabel() }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="text-end shrink-0">
                            <div class="text-lg font-bold text-zinc-900 dark:text-white tabular-nums">{{ $entry['percent'] }}%</div>
                            <div class="text-[11px] text-zinc-500 dark:text-zinc-400 tabular-nums">
                                {{ rtrim(rtrim(number_format($entry['done'], 2), '0'), '.') }}
                                {{ __('من') }}
                                {{ rtrim(rtrim(number_format($entry['target'], 2), '0'), '.') }}
                                {{ $item->displayUnit() }}
                            </div>
                        </div>
                    </div>

                    <div class="h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                        <div class="h-full rounded-full bg-sky-500 transition-all duration-500" style="width: {{ $entry['percent'] }}%"></div>
                    </div>

                    @if ($entry['target'] > 0 && ($bridged[$item->id] ?? false))
                        {{-- يكتبه التسميع، فلا يؤكده الطالب مرة ثانية --}}
                        <div class="flex items-center gap-2 rounded-xl bg-zinc-50 dark:bg-zinc-800/60 px-3 py-2.5">
                            <flux:icon icon="check-badge" class="size-4 text-emerald-500 shrink-0" />
                            <div class="text-xs text-zinc-600 dark:text-zinc-300 leading-relaxed">
                                {{ __('يُسجَّل تلقائياً من تسميعك عند معلمك') }} —
                                <span class="font-bold tabular-nums">
                                    {{ rtrim(rtrim(number_format($entry['done'], 2), '0'), '.') }} {{ $item->displayUnit() }}
                                </span>
                                {{ __('حتى الآن، والمقترح اليوم') }}
                                <span class="font-bold tabular-nums">
                                    {{ rtrim(rtrim(number_format($suggested, 2), '0'), '.') }}
                                </span>.
                            </div>
                        </div>
                    @elseif ($entry['target'] > 0)
                        <div class="flex items-end gap-2">
                            <flux:field class="flex-1">
                                <flux:label class="text-xs">
                                    {{ __('المقترح اليوم') }}:
                                    <span class="font-bold text-zinc-700 dark:text-zinc-200 tabular-nums">
                                        {{ rtrim(rtrim(number_format($suggested, 2), '0'), '.') }} {{ $item->displayUnit() }}
                                    </span>
                                </flux:label>
                                <flux:input type="number" step="0.25" min="0"
                                    wire:model="amounts.{{ $item->id }}"
                                    placeholder="{{ __('ما أنجزته') }}" />
                                <flux:error name="amounts.{{ $item->id }}" />
                            </flux:field>
                            <flux:button variant="primary" wire:click="save({{ $item->id }})" class="mb-0.5">
                                {{ __('تأكيد') }}
                            </flux:button>
                        </div>
                    @else
                        <div class="text-xs text-zinc-400 dark:text-zinc-500">
                            {{ __('لم يُطلب هذا المجال هذا الأسبوع.') }}
                        </div>
                    @endif
                </flux:card>
            @endforeach
        </div>

        {{-- البرنامج الإثرائي --}}
        @if ($enrichment && $enrichmentProgress)
            <flux:card class="border-emerald-200 dark:border-emerald-900/50">
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-zinc-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2.5">
                        <div class="p-2 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400">
                            <flux:icon icon="sparkles" class="size-5" />
                        </div>
                        <div>
                            <flux:heading size="lg">{{ __('البرنامج الإثرائي') }}</flux:heading>
                            <flux:subheading class="mt-0.5">
                                {{ __('فُتح لك لبلوغك نصف برنامجك الذاتي — من معلّم دفعتك.') }}
                            </flux:subheading>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                            {{ $enrichmentProgress['overall'] }}%
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($enrichmentProgress['tracks'] as $extra)
                        @if ($extra['target'] > 0)
                            <div wire:key="extra-{{ $extra['item']->id }}" class="py-3 flex flex-wrap items-end justify-between gap-3">
                                <div class="flex-1 min-w-48">
                                    <div class="text-sm font-bold text-zinc-900 dark:text-white">
                                        {{ $extra['item']->track->label() }}
                                        <span class="text-xs font-medium text-zinc-400">
                                            — {{ $extra['item']->description ?: __('محتوى إثرائي') }}
                                        </span>
                                    </div>
                                    <div class="mt-1.5 h-2 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ $extra['percent'] }}%"></div>
                                    </div>
                                    <div class="text-[11px] text-zinc-500 dark:text-zinc-400 tabular-nums mt-1">
                                        {{ rtrim(rtrim(number_format($extra['done'], 2), '0'), '.') }}
                                        {{ __('من') }}
                                        {{ rtrim(rtrim(number_format($extra['target'], 2), '0'), '.') }}
                                        {{ $extra['item']->displayUnit() }}
                                    </div>
                                </div>
                                <div class="flex items-end gap-2">
                                    <flux:field>
                                        <flux:input type="number" step="0.25" min="0" class="w-32"
                                            wire:model="amounts.{{ $extra['item']->id }}"
                                            placeholder="{{ __('ما أنجزته') }}" />
                                        <flux:error name="amounts.{{ $extra['item']->id }}" />
                                    </flux:field>
                                    <flux:button variant="filled" wire:click="save({{ $extra['item']->id }})" class="mb-0.5">
                                        {{ __('تأكيد') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </flux:card>
        @endif
    @endif
</div>
