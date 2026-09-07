<?php

use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Services\SelfProgramService;
use App\Models\SelfProgramTrack;
use Carbon\Carbon;
use Flux\Flux;
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
    /** Which scale the grid is read at: the week, the month, or the term. */
    public string $scale = 'week';

    /** The field waiting on "have you recited it?", if one is. */
    public ?int $askingRecitationFor = null;

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

        $amount = (float) ($this->amounts[$itemId] ?? 0);

        // Memorising is not knowing until somebody has heard it, so the field
        // asks before it counts. Clearing an entry needs no hearing.
        if ($amount > 0 && $item->track?->needs_recitation_confirmation) {
            $this->askingRecitationFor = $itemId;

            return;
        }

        $this->write($item, $amount);
    }

    /** He says he recited it: the amount counts and its points are given. */
    public function confirmRecitation(): void
    {
        $item = $this->openItems()->firstWhere('id', $this->askingRecitationFor);

        abort_unless($item instanceof SelfProgramItem, 404);

        $this->write($item, (float) ($this->amounts[$item->id] ?? 0), true);

        $this->askingRecitationFor = null;
    }

    /**
     * He says he has not: nothing is written at all.
     *
     * Not a zero either — a zero is a statement that he did none of it, and
     * what he is saying is that he did it and has not recited it yet.
     */
    public function denyRecitation(): void
    {
        $this->askingRecitationFor = null;

        Flux::toast(__('لم يُحتسب — سمّعه ثم سجّله.'), variant: 'warning');
    }

    private function write(SelfProgramItem $item, float $amount, bool $recited = false): void
    {
        try {
            app(SelfProgramService::class)->record(
                Auth::guard('student')->user(),
                $item,
                $amount,
                Carbon::parse($this->day),
                recitationConfirmed: $recited,
            );
        } catch (\InvalidArgumentException $e) {
            Flux::toast($e->getMessage(), variant: 'danger');
        }
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
                'grid' => ['days' => [], 'columns' => [], 'rows' => []], 'wide' => null,
            ];
        }

        $progress = $service->weekProgress($student, $week);

        // The week as it was written: a row per field, a column per day. The
        // wider scales are not read that way — thirty columns are not a table
        // anybody reads — so there the column becomes the period.
        $grid = $service->weekGrid($student, $week);

        $today = Carbon::today();

        $wide = match ($this->scale) {
            'month' => $service->periodGrid(
                $student,
                $today->copy()->startOfMonth()->format('Y-m-d'),
                $today->copy()->endOfMonth()->format('Y-m-d'),
                'week',
            ),
            'term' => $service->periodGrid(
                $student,
                $today->copy()->subMonths(3)->startOfMonth()->format('Y-m-d'),
                $today->copy()->endOfMonth()->format('Y-m-d'),
                'month',
            ),
            default => null,
        };
        $enrichment = $this->enrichmentWeek();

        $plans = [];
        $bridged = [];

        foreach ($week->items as $item) {
            $plans[$item->id] = $service->dailyPlan($item, $student);
            $bridged[$item->id] = $this->bridged($item);
        }

        return [
            'week' => $week,
            'grid' => $grid,
            'wide' => $wide,
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


    @if ($askingRecitationFor)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 shadow-xl p-6 space-y-4 text-center">
                <div class="mx-auto w-fit p-3 rounded-2xl bg-maroon/10 text-maroon dark:text-red-secondary">
                    <flux:icon icon="microphone" class="size-6" />
                </div>

                <div>
                    <div class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('هل سمّعت المحفوظ؟') }}</div>
                    <div class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                        {{ __('لا يُحتسب المحفوظ ولا تُعطى درجته حتى تُسمّعه.') }}
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button class="flex-1 !bg-maroon hover:!bg-burgundy" variant="primary" wire:click="confirmRecitation">
                        {{ __('نعم، سمّعته') }}
                    </flux:button>
                    <flux:button class="flex-1" variant="ghost" wire:click="denyRecitation">
                        {{ __('ليس بعد') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    {{-- جدول الأسبوع --}}
    @if (! empty($grid['days']))
        <flux:card class="space-y-3">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <flux:heading size="lg">
                        {{ ['week' => __('جدول أسبوعك'), 'month' => __('شهرك'), 'term' => __('فصلك')][$scale] }}
                    </flux:heading>
                    <flux:subheading class="mt-0.5">
                        {{ $scale === 'week'
                            ? __('ما هو مقرَّر لكل يوم، وما أنجزته منه. والخانة الفارغة يومٌ لم يُقرَّر لك فيه شيء.')
                            : __('المنجَز من المطلوب في كل فترة.') }}
                    </flux:subheading>
                </div>

                <div class="flex items-center rounded-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden shrink-0">
                    @foreach (['week' => __('أسبوع'), 'month' => __('شهر'), 'term' => __('٤ أشهر')] as $value => $label)
                        <button wire:click="$set('scale', '{{ $value }}')" wire:key="scale-{{ $value }}"
                            class="px-3 py-1.5 text-xs font-bold transition-colors
                                {{ $scale === $value
                                    ? 'bg-maroon text-white'
                                    : 'text-zinc-500 hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            @if ($scale !== 'week')
                @if ($wide && $wide['rows'] !== [])
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr>
                                    <th class="p-2 text-right text-xs font-bold text-zinc-500 sticky start-0 bg-white dark:bg-zinc-900">
                                        {{ __('المجال') }}
                                    </th>
                                    @foreach ($wide['columns'] as $column)
                                        <th class="p-2 text-center text-xs font-bold text-zinc-500 min-w-28">{{ $column['label'] }}</th>
                                    @endforeach
                                    <th class="p-2 text-center text-xs font-bold text-zinc-500">{{ __('المجموع') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($wide['rows'] as $row)
                                    <tr class="border-t border-zinc-100 dark:border-zinc-800" wire:key="wide-{{ $row['track']->key }}">
                                        <td class="p-2 font-bold text-zinc-800 dark:text-zinc-100 whitespace-nowrap sticky start-0 bg-white dark:bg-zinc-900">
                                            {{ $row['track']->label() }}
                                        </td>
                                        @foreach ($wide['columns'] as $column)
                                            @php
                                                $cell = $row['cells'][$column['key']];
                                                $met = $cell['target'] > 0 && $cell['done'] >= $cell['target'];
                                                $partly = ! $met && $cell['done'] > 0;
                                            @endphp
                                            <td class="p-2 text-center text-[11px] tabular-nums
                                                {{ $met ? 'bg-emerald-50/60 dark:bg-emerald-950/20 text-emerald-700 dark:text-emerald-400' : ($partly ? 'bg-amber-50/60 dark:bg-amber-950/20' : 'text-zinc-500') }}">
                                                @if ($cell['target'] > 0)
                                                    {{ rtrim(rtrim(number_format($cell['done'], 2, '.', ''), '0'), '.') }}
                                                    /
                                                    {{ rtrim(rtrim(number_format($cell['target'], 2, '.', ''), '0'), '.') }}
                                                @else
                                                    <span class="text-zinc-300 dark:text-zinc-700">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="p-2 text-center text-[11px] font-bold tabular-nums text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                            {{ rtrim(rtrim(number_format($row['done'], 2, '.', ''), '0'), '.') }}
                                            /
                                            {{ rtrim(rtrim(number_format($row['target'], 2, '.', ''), '0'), '.') }}
                                            <span class="block text-[10px] font-normal text-zinc-400">{{ $row['unit'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <flux:text class="text-zinc-400">{{ __('لا برنامج مكتوب في هذه الفترة.') }}</flux:text>
                @endif
            @else

            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr>
                            <th class="p-2 text-right text-xs font-bold text-zinc-500 sticky start-0 bg-white dark:bg-zinc-900">
                                {{ __('المجال') }}
                            </th>
                            @foreach ($grid['days'] as $day)
                                <th class="p-2 text-center text-xs font-bold min-w-32
                                    {{ $day === now('Asia/Riyadh')->format('Y-m-d') ? 'text-maroon dark:text-red-secondary' : 'text-zinc-500' }}">
                                    <x-hijri-date :date="$day" style="weekdayOnly" />
                                    <span class="block text-[10px] font-normal text-zinc-400" dir="ltr">{{ substr($day, 5) }}</span>
                                </th>
                            @endforeach
                            <th class="p-2 text-center text-xs font-bold text-zinc-500">{{ __('الأسبوع') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grid['rows'] as $row)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800" wire:key="grid-row-{{ $row['item']->id }}">
                                <td class="p-2 font-bold text-zinc-800 dark:text-zinc-100 whitespace-nowrap sticky start-0 bg-white dark:bg-zinc-900">
                                    {{ $row['track']?->label() }}
                                </td>

                                @foreach ($grid['days'] as $day)
                                    @php
                                        $cell = $row['cells'][$day];
                                        $met = $cell['expected'] > 0 && $cell['done'] >= $cell['expected'];
                                        $partly = ! $met && $cell['done'] > 0;
                                    @endphp
                                    <td class="p-2 text-center align-top
                                        {{ $met ? 'bg-emerald-50/60 dark:bg-emerald-950/20' : ($partly ? 'bg-amber-50/60 dark:bg-amber-950/20' : '') }}">
                                        @if ($cell['content'])
                                            <div class="text-xs text-zinc-700 dark:text-zinc-200 leading-snug">{{ $cell['content'] }}</div>
                                        @endif

                                        @if ($cell['expected'] > 0)
                                            <div class="mt-1 text-[11px] tabular-nums {{ $met ? 'text-emerald-700 dark:text-emerald-400' : 'text-zinc-500' }}">
                                                {{ rtrim(rtrim(number_format($cell['done'], 2, '.', ''), '0'), '.') }}
                                                /
                                                {{ rtrim(rtrim(number_format($cell['expected'], 2, '.', ''), '0'), '.') }}
                                            </div>
                                        @elseif (! $cell['content'])
                                            <span class="text-zinc-300 dark:text-zinc-700">—</span>
                                        @endif
                                    </td>
                                @endforeach

                                <td class="p-2 text-center text-[11px] font-bold tabular-nums text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                    {{ rtrim(rtrim(number_format($row['done'], 2, '.', ''), '0'), '.') }}
                                    /
                                    {{ rtrim(rtrim(number_format($row['target'], 2, '.', ''), '0'), '.') }}
                                    <span class="block text-[10px] font-normal text-zinc-400">{{ $row['unit'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-4 text-[11px] text-zinc-400 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded bg-emerald-200 dark:bg-emerald-900"></span>{{ __('أُنجز') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded bg-amber-200 dark:bg-amber-900"></span>{{ __('بعضه') }}
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="text-zinc-300 dark:text-zinc-700">—</span>{{ __('لا شيء لهذا اليوم') }}
                </span>
            </div>
            @endif
        </flux:card>
    @endif

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
