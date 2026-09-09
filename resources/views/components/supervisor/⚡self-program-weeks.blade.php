<?php

use App\Models\Circle;
use App\Models\SelfProgramDayOverride;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramWeek;
use App\Services\SelfProgramService;
use App\Services\SelfProgramYearBuilder;
use App\Models\Stage;
use App\Support\SelfProgramSheet;
use App\Support\SelfProgramUnit;
use App\Models\SelfProgramTrack;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use WithFileUploads;

    public ?int $stageId = null;

    /** The cohort a teacher writes for. Null for the offices that write for a programme. */
    public ?int $circleId = null;

    /** The day grid: [trackKey][date] => ['content' => ?string, 'amount' => ?string]. */
    public array $grid = [];

    public bool $showGrid = true;

    /** Days ticked in the grid header, waiting to be joined into one column. */
    public array $selectedDays = [];

    public bool $showPreview = false;

    /** A table pasted from a sheet, waiting to be spread over the grid. */
    public string $pasted = '';

    /** Whether the year-at-once tools are showing. */
    public bool $showYearTools = false;

    public string $yearStartsOn = '';

    public int $yearWeeks = 36;

    /** A yearly total per field, for the tool that divides one out. */
    public array $annual = [];

    public $sheet = null;

    /** @var array<int, string> */
    public array $importErrors = [];

    public ?int $weekId = null;

    /** Editable fields of the open week's five tracks, keyed by track value. */
    public array $rows = [];

    public string $newStartsOn = '';

    /**
     * The office this page was opened under.
     *
     * The manager carries the supervisor and opens this through `manager.held`,
     * signed in on his own guard and not the supervisor's — so naming that
     * guard answered for nobody and the page died in his hands.
     */
    public string $asRole = 'supervisor';

    public function mount(): void
    {
        $this->asRole = \App\Support\Scope::resolveRole();

        if ($this->writesForCohort()) {
            $this->circleId = $this->cohorts->first()?->id;
            $this->stageId = $this->cohorts->first()?->stage_id;
        } else {
            $this->stageId = $this->stages->first()?->id;
        }
        $this->newStartsOn = Carbon::today()->startOfWeek(Carbon::SUNDAY)->toDateString();
        $this->yearStartsOn = $this->newStartsOn;
        $this->openLatestWeek();
    }

    /**
     * The programmes this reader writes for.
     *
     * A supervisor writes for his own and no others; an office above him that
     * reaches the whole academy writes for any of them. Asked of `Scope`, which
     * is where that question is answered for every other screen.
     *
     * @return Collection<int, Stage>
     */
    #[Computed]
    public function stages(): Collection
    {
        $reach = \App\Support\Scope::forRole($this->asRole)->stageIds();

        return $reach === null
            ? Stage::orderBy('name')->get()
            : Stage::whereIn('id', $reach)->orderBy('name')->get();
    }

    /**
     * Whether this reader writes for one cohort rather than for a programme.
     *
     * The supervisor writes the programme and it reaches every cohort in it.
     * A teacher writes for his own, and a week naming a cohort is the one his
     * students read — see `SelfProgramService::currentWeek`.
     */
    public function writesForCohort(): bool
    {
        return $this->asRole === 'teacher';
    }

    /** @return Collection<int, Circle> */
    #[Computed]
    public function cohorts(): Collection
    {
        $user = \App\Support\Scope::forRole($this->asRole)->user();

        return $user
            ? $user->circles()->orderBy('name')->get()
            : collect();
    }

    public function chooseCohort(int $circleId): void
    {
        $cohort = $this->cohorts->firstWhere('id', $circleId) ?? abort(403);

        $this->circleId = $cohort->id;
        $this->stageId = $cohort->stage_id;

        unset($this->weeks);
        $this->openLatestWeek();
    }

    private function author(): ?\App\Models\User
    {
        return \App\Support\Scope::forRole($this->asRole)->user();
    }

    /**
     * How much of each week has actually been written.
     *
     * Thirty buttons reading "الأسبوع ١، الأسبوع ٢" tell nobody which of them
     * were filled and which were generated empty and left, so the supervisor
     * navigated a year by guessing.
     *
     * @return array<int, array{filled: int, of: int, days: int}>
     */
    #[Computed]
    public function weekState(): array
    {
        $weeks = $this->weeks;

        if ($weeks->isEmpty()) {
            return [];
        }

        $items = SelfProgramItem::whereIn('self_program_week_id', $weeks->pluck('id'))->get();

        $days = SelfProgramDayOverride::whereIn('self_program_item_id', $items->pluck('id'))
            ->when($this->writesForCohort(),
                fn ($q) => $q->whereNull('student_id')->where('circle_id', $this->circleId),
                fn ($q) => $q->whereNull('student_id')->whereNull('circle_id'))
            ->get()
            ->groupBy('self_program_item_id');

        $state = [];

        foreach ($weeks as $week) {
            $mine = $items->where('self_program_week_id', $week->id);

            $state[$week->id] = [
                // A field asked for in any amount is a field that was written.
                'filled' => $mine->filter(fn (SelfProgramItem $item) => (float) $item->target_amount > 0)->count(),
                'of' => $mine->count(),
                'days' => $mine->sum(fn (SelfProgramItem $item) => ($days[$item->id] ?? collect())->count()),
            ];
        }

        return $state;
    }

    /** The week covering today, so a year is opened where the academy is in it. */
    #[Computed]
    public function currentWeekId(): ?int
    {
        $today = Carbon::today()->toDateString();

        return $this->weeks
            ->first(fn (SelfProgramWeek $week) => $week->starts_on->toDateString() <= $today
                && $week->ends_on->toDateString() >= $today)?->id;
    }

    /** @return Collection<int, SelfProgramWeek> */
    #[Computed]
    public function weeks(): Collection
    {
        if (! $this->stageId) {
            return collect();
        }

        return SelfProgramWeek::self()
            ->where('stage_id', $this->stageId)
            ->when($this->writesForCohort(),
                fn ($q) => $q->where('circle_id', $this->circleId),
                fn ($q) => $q->whereNull('circle_id'))
            ->orderBy('week_number')
            ->get();
    }

    #[Computed]
    public function week(): ?SelfProgramWeek
    {
        return $this->weekId
            ? SelfProgramWeek::with('items')->find($this->weekId)
            : null;
    }

    public function updatedStageId(): void
    {
        unset($this->weeks);
        $this->openLatestWeek();
    }

    private function openLatestWeek(): void
    {
        $this->weekId = $this->weeks->last()?->id;
        $this->loadRows();
    }

    public function openWeek(int $id): void
    {
        $this->authorizeStage($id);
        $this->weekId = $id;
        $this->loadRows();
        $this->loadGrid();
    }

    /**
     * A week only ever belongs to one stage, and a supervisor may only touch the
     * stages he is assigned to.
     */
    private function authorizeStage(int $weekId): void
    {
        $week = SelfProgramWeek::findOrFail($weekId);

        abort_unless($this->stages->contains('id', $week->stage_id), 403);
    }

    private function loadRows(): void
    {
        $this->rows = [];
        $week = $this->week;

        if (! $week) {
            return;
        }

        $week->ensureAllTracks();
        $week->load('items');
        unset($this->week);

        foreach (SelfProgramTrack::ordered() as $track) {
            $item = $week->items->firstWhere('track.key', $track->key);

            $amount = $item ? (float) $item->target_amount : 0;
            $clock = SelfProgramUnit::toHoursAndMinutes($amount);

            $this->rows[$track->value] = [
                'description' => $item?->description ?? '',
                'content_url' => $item?->content_url ?? '',
                'target_amount' => $amount,
                'unit' => $track->unitFor($item?->unit),
                // Time is asked for the way it is spoken, and kept in minutes.
                'hours' => $clock['hours'],
                'minutes' => $clock['minutes'],
                // A week written before the field had a vocabulary keeps the
                // word it was written in, said aloud rather than quietly
                // reinterpreted — three lessons are not three minutes.
                'was_written_in' => $item && $item->unit && ! $track->allowsUnit($item->unit) ? $item->unit : null,
            ];
        }
    }

    /**
     * Add the week that follows the last one, seven days after it.
     */
    /**
     * Spread a table pasted from a sheet over the grid.
     *
     * Word and Excel both put a tab between cells and a newline between rows,
     * so a week copied out of the academy's own document lands here whole
     * rather than being retyped into thirty-five boxes.
     *
     * The first cell of a row names the field — by its label or its key — and
     * the rest fall on the week's days in order. A row naming nothing is
     * skipped rather than guessed at.
     */
    public function applyPaste(): void
    {
        if (trim($this->pasted) === '') {
            return;
        }

        $columns = app(SelfProgramService::class)->dayColumns($this->week);
        $keys = array_column($columns, 'key');
        $tracks = SelfProgramTrack::ordered();
        $placed = 0;

        foreach (preg_split('/\r\n|\r|\n/', $this->pasted) as $line) {
            $cells = preg_split('/\t|\s{2,}|\|/', trim($line));

            if (count($cells) < 2) {
                continue;
            }

            $name = trim(array_shift($cells));

            $track = $tracks->first(fn (SelfProgramTrack $t) => $t->key === $name || $t->label() === $name);

            if (! $track) {
                continue;
            }

            foreach (array_values($cells) as $index => $value) {
                if (! isset($keys[$index])) {
                    break;
                }

                $value = trim($value);

                // A dash is how the academy's sheet writes "nothing today".
                $this->grid[$track->key][$keys[$index]]['content'] = in_array($value, ['-', '—', '－', '...', '……'], true)
                    ? ''
                    : $value;
            }

            $placed++;
        }

        $this->pasted = '';

        Flux::toast(
            text: $placed > 0 ? "وُزّع {$placed} صفّاً — راجعها ثم احفظ." : 'لم يُتعرَّف على أي مجال في ما لصقت.',
            variant: $placed > 0 ? 'success' : 'danger',
        );
    }

    /**
     * Join the ticked days into one column.
     *
     * A weekend run together, or the three days of a trip: the student is asked
     * for one amount across them rather than a share of each. Days already in a
     * group are pulled out of it first, so joining two of three leaves the third
     * standing on its own rather than in a group of one.
     */
    public function mergeSelected(): void
    {
        $week = $this->week;

        if (! $week || count($this->selectedDays) < 2) {
            Flux::toast(text: 'اختر يومين فأكثر.', variant: 'danger');

            return;
        }

        abort_unless($this->stages->contains('id', $this->stageId), 403);

        $chosen = array_values(array_unique($this->selectedDays));
        sort($chosen);

        $groups = collect($week->merged_days ?? [])
            ->map(fn ($group) => array_values(array_diff((array) $group, $chosen)))
            ->filter(fn (array $group) => count($group) > 1)
            ->values()
            ->push($chosen)
            ->all();

        $week->update(['merged_days' => $groups]);

        $this->selectedDays = [];
        unset($this->weeks);

        Flux::toast(text: 'دُمجت الأيام.', variant: 'success');
    }

    /** Undo one grouping, leaving its days standing on their own again. */
    public function unmerge(string $firstDay): void
    {
        $week = $this->week;

        if (! $week) {
            return;
        }

        abort_unless($this->stages->contains('id', $this->stageId), 403);

        $groups = collect($week->merged_days ?? [])
            ->reject(fn ($group) => ((array) $group)[0] === $firstDay)
            ->values()
            ->all();

        $week->update(['merged_days' => $groups]);

        unset($this->weeks);

        Flux::toast(text: 'فُكّ الدمج.', variant: 'success');
    }

    /**
     * Read the week's day plan into the form.
     *
     * Only the rows this office wrote: a supervisor sees the programme's own
     * plan, a teacher his cohort's. Neither edits the other's by accident —
     * what a teacher writes sits on top of the programme's and leaves it whole.
     */
    public function loadGrid(): void
    {
        $this->grid = [];

        $week = $this->week;

        if (! $week) {
            return;
        }

        $days = app(SelfProgramService::class)->workingDays($week);

        $written = SelfProgramDayOverride::whereIn('self_program_item_id', $week->items->pluck('id'))
            ->when($this->writesForCohort(),
                fn ($q) => $q->whereNull('student_id')->where('circle_id', $this->circleId),
                fn ($q) => $q->whereNull('student_id')->whereNull('circle_id'))
            ->get()
            ->keyBy(fn (SelfProgramDayOverride $row) => $row->self_program_item_id.'|'.$row->day_date->toDateString());

        foreach ($week->items as $item) {
            $key = $item->track?->key ?? (string) $item->id;

            foreach ($days as $day) {
                $row = $written[$item->id.'|'.$day] ?? null;

                $this->grid[$key][$day] = [
                    'content' => $row?->content ?? '',
                    'amount' => $row && $row->amount !== null ? (string) (float) $row->amount : '',
                ];
            }
        }
    }

    /**
     * Write the day plan back.
     *
     * A cell left empty in both fields is not stored: absence is how a day is
     * said to hold nothing, and the sheet the academy writes has deliberate
     * blanks in it.
     */
    public function saveGrid(): void
    {
        $week = $this->week;

        if (! $week) {
            return;
        }

        abort_unless($this->stages->contains('id', $this->stageId), 403);

        $circleId = $this->writesForCohort() ? $this->circleId : null;

        foreach ($week->items as $item) {
            $key = $item->track?->key ?? (string) $item->id;

            foreach ($this->grid[$key] ?? [] as $day => $cell) {
                $content = trim((string) ($cell['content'] ?? ''));
                $amount = trim((string) ($cell['amount'] ?? ''));

                // Matched as a date and not as text: the cast writes
                // `Y-m-d H:i:s`, so a plain comparison never finds the row it
                // wrote a moment ago — and updateOrCreate would go on making a
                // second one for the same day.
                $existing = SelfProgramDayOverride::where('self_program_item_id', $item->id)
                    ->whereDate('day_date', $day)
                    ->whereNull('student_id')
                    ->when($circleId, fn ($q) => $q->where('circle_id', $circleId), fn ($q) => $q->whereNull('circle_id'))
                    ->first();

                if ($content === '' && $amount === '') {
                    $existing?->delete();

                    continue;
                }

                $values = [
                    'content' => $content ?: null,
                    // A day's share is measured in the same unit as its week,
                    // so half a hadith cannot enter through the grid either.
                    'amount' => $amount === '' ? null : SelfProgramUnit::normalise((float) $amount, $item->unit),
                ];

                if ($existing) {
                    $existing->update($values);

                    continue;
                }

                SelfProgramDayOverride::create($values + [
                    'self_program_item_id' => $item->id,
                    'day_date' => $day,
                    'circle_id' => $circleId,
                    'student_id' => null,
                ]);
            }
        }

        Flux::toast(text: 'حُفظ الجدول اليومي.', variant: 'success');
    }

    public function addWeek(): void
    {
        $this->validate([
            'stageId' => ['required'],
            'newStartsOn' => ['required', 'date'],
        ], [], ['newStartsOn' => 'تاريخ البداية']);

        abort_unless($this->stages->contains('id', $this->stageId), 403);

        $starts = Carbon::parse($this->newStartsOn)->startOfDay();
        $ends = $starts->copy()->addDays(6);

        // Two weeks over the same days would leave the student's «which week is
        // mine» answered by whichever the database returned first: he would read
        // one programme and be measured against the other.
        $clash = SelfProgramWeek::clashOn(
            $this->stageId,
            $this->writesForCohort() ? $this->circleId : null,
            SelfProgramWeek::TYPE_SELF,
            $starts->toDateString(),
            $ends->toDateString(),
        );

        if ($clash) {
            Flux::toast(
                text: __('الأسبوع :n يغطّي هذه الأيام بالفعل (:from — :to). ابدأ بعده أو عدّله.', [
                    'n' => $clash->week_number,
                    'from' => $clash->starts_on->toDateString(),
                    'to' => $clash->ends_on->toDateString(),
                ]),
                variant: 'warning',
            );

            return;
        }

        $week = SelfProgramWeek::create([
            'stage_id' => $this->stageId,
            'circle_id' => $this->writesForCohort() ? $this->circleId : null,
            'program_type' => SelfProgramWeek::TYPE_SELF,
            'week_number' => ($this->weeks->max('week_number') ?? 0) + 1,
            'starts_on' => $starts,
            'ends_on' => $ends,
            'created_by_id' => $this->author()?->id,
            'created_by_type' => $this->author() ? $this->author()::class : null,
        ]);

        $week->ensureAllTracks();

        unset($this->weeks);
        $this->weekId = $week->id;
        $this->newStartsOn = $starts->copy()->addDays(7)->toDateString();
        $this->loadRows();

        Flux::toast(text: 'أُضيف الأسبوع.', variant: 'success');
    }

    public function save(): void
    {
        $week = $this->week;

        abort_unless($week && $this->stages->contains('id', $week->stage_id), 403);

        $this->validate([
            'rows.*.description' => ['nullable', 'string', 'max:500'],
            'rows.*.target_amount' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'rows.*.unit' => ['nullable', 'string', 'max:30'],
            'rows.*.hours' => ['nullable', 'integer', 'min:0', 'max:99'],
            'rows.*.minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'rows.*.content_url' => ['nullable', 'url', 'max:2048'],
        ]);

        foreach (SelfProgramTrack::ordered() as $track) {
            $row = $this->rows[$track->value] ?? null;

            if (! $row) {
                continue;
            }

            // The unit settles first, because it decides how the amount was
            // asked for: time comes in two boxes, everything else in one.
            $unit = $track->unitFor($row['unit'] ?? null);

            $amount = SelfProgramUnit::isDuration($unit)
                ? SelfProgramUnit::fromHoursAndMinutes($row['hours'] ?? 0, $row['minutes'] ?? 0)
                : SelfProgramUnit::normalise((float) ($row['target_amount'] ?: 0), $unit);

            $week->items()->updateOrCreate(
                ['track' => $track->value],
                [
                    'description' => $row['description'] ?: null,
                    // The Quran is in the application already; a link out of it
                    // would be a step backwards, so the wird never carries one.
                    'content_url' => $track->isQuranWird()
                        ? null
                        : (($row['content_url'] ?? '') ?: null),
                    'target_amount' => $amount,
                    'unit' => $unit,
                ],
            );
        }

        unset($this->week);

        Flux::toast(text: 'حُفظ محتوى الأسبوع.', variant: 'success');
    }

    /**
     * Lay the year out as blank weeks, ready to be filled by any of the routes
     * below. Weeks the academy does not meet in are skipped, not created empty.
     */
    public function generateYear(): void
    {
        $this->guardStage();

        $this->validate([
            'yearStartsOn' => ['required', 'date'],
            'yearWeeks' => ['required', 'integer', 'min:1', 'max:60'],
        ], [], ['yearStartsOn' => 'تاريخ البداية', 'yearWeeks' => 'عدد الأسابيع']);

        $result = app(SelfProgramYearBuilder::class)->generate(
            Carbon::parse($this->yearStartsOn),
            $this->yearWeeks,
            $this->stageId,
            $this->writesForCohort() ? $this->circleId : null,
        );

        unset($this->weeks);
        $this->openLatestWeek();

        Flux::toast(
            text: "أُضيف {$result['created']} أسبوعاً، وتُخطّي {$result['skipped']} خارج الدوام.",
            variant: 'success',
        );
    }

    /**
     * Write the open week's five fields onto every other week of the year.
     */
    public function copyAcrossYear(): void
    {
        $this->guardStage();
        $week = $this->week;

        abort_unless($week !== null, 404);

        $written = app(SelfProgramYearBuilder::class)->copyAcross($week, $this->weeks);

        unset($this->week);

        Flux::toast(text: "نُسخ المحتوى على {$written} أسبوعاً.", variant: 'success');
    }

    /**
     * Divide a year's total for each field evenly across the year's weeks.
     */
    public function distributeYear(): void
    {
        $this->guardStage();

        $this->validate(['annual.*' => ['nullable', 'numeric', 'min:0', 'max:99999']]);

        app(SelfProgramYearBuilder::class)->distribute($this->annual, $this->weeks);

        unset($this->week);
        $this->loadRows();

        Flux::toast(text: 'وُزّع المقدار السنوي على الأسابيع.', variant: 'success');
    }

    /**
     * Read a handed-over sheet onto the year's weeks.
     */
    public function importSheet(): void
    {
        $this->guardStage();

        $this->validate(
            ['sheet' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:4096']],
            [],
            ['sheet' => 'الملف'],
        );

        $result = app(SelfProgramYearBuilder::class)->import(
            $this->sheet->getRealPath(),
            $this->stageId,
            $this->writesForCohort() ? $this->circleId : null,
            SelfProgramWeek::TYPE_SELF,
            $this->sheet->getClientOriginalExtension(),
        );

        $this->importErrors = $result['errors'];
        $this->sheet = null;

        unset($this->week);
        $this->loadRows();

        Flux::toast(
            text: "قُرئ {$result['written']} بنداً".($result['errors'] === [] ? '.' : ' مع ملاحظات.'),
            variant: $result['errors'] === [] ? 'success' : 'warning',
        );
    }

    /**
     * Hand back a blank sheet shaped the way the reader expects.
     */
    public function downloadTemplate(): StreamedResponse
    {
        $weeks = max(1, $this->weeks->count() ?: $this->yearWeeks);

        return response()->streamDownload(
            fn () => print SelfProgramSheet::template($weeks),
            'self-program-template.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function guardStage(): void
    {
        abort_unless($this->stageId && $this->stages->contains('id', $this->stageId), 403);
    }

    public function with(): array
    {
        return ['tracks' => SelfProgramTrack::ordered()];
    }
};
?>

<div class="space-y-6" dir="rtl">
    @if ($this->writesForCohort())
        <div class="rounded-xl border border-emerald-200/70 dark:border-emerald-900/40 bg-emerald-50/60 dark:bg-emerald-950/20 p-4 mb-4 space-y-3">
            <div>
                <div class="text-sm font-bold text-emerald-900 dark:text-emerald-200">{{ __('أنت تكتب لدفعتك وحدها') }}</div>
                <div class="text-xs text-emerald-800/80 dark:text-emerald-300/80 mt-1">
                    {{ __('ما تحفظه هنا يقرؤه طلاب دفعتك دون غيرهم، ويتقدّم على ما كتبه المشرف للبرنامج. واتركه فارغاً ليقرؤوا برنامج المشرف كما هو.') }}
                </div>
            </div>

            @if ($this->cohorts->count() > 1)
                <div class="flex items-center gap-2 flex-wrap">
                    @foreach ($this->cohorts as $one)
                        <button wire:click="chooseCohort({{ $one->id }})" wire:key="c-{{ $one->id }}"
                            class="px-3 py-1.5 text-xs font-bold rounded-lg border transition-colors
                                {{ $circleId === $one->id ? 'bg-maroon text-white border-maroon' : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300' }}">
                            {{ $one->name }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if ($this->stages->isEmpty())
        <flux:card class="text-center py-12">
            <flux:icon icon="exclamation-triangle" class="size-10 mx-auto text-amber-400" />
            <flux:heading size="lg" class="mt-3">{{ __('لا توجد برامج مسندة إليك') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('محتوى البرنامج الذاتي يُكتب لكل برنامج على حدة.') }}</flux:subheading>
        </flux:card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <flux:field>
                <flux:label>{{ __('البرنامج') }}</flux:label>
                <flux:select wire:model.live="stageId">
                    @foreach ($this->stages as $stage)
                        <flux:select.option value="{{ $stage->id }}">{{ $stage->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:field>

            <div class="flex items-end gap-2">
                <flux:field class="flex-1">
                    <flux:label>{{ __('بداية أسبوع جديد') }}</flux:label>
                    <flux:input type="date" wire:model="newStartsOn" />
                    <flux:error name="newStartsOn" />
                </flux:field>
                <flux:button variant="primary" wire:click="addWeek" class="mb-0.5" icon="plus">
                    {{ __('أضف أسبوعاً') }}
                </flux:button>
            </div>
        </div>


        {{-- أدوات السنة كاملة --}}
        <flux:card>
            <button type="button" wire:click="$toggle('showYearTools')"
                class="w-full flex items-center justify-between gap-3 text-start cursor-pointer">
                <div>
                    <flux:heading size="lg">{{ __('إعداد السنة كاملة') }}</flux:heading>
                    <flux:subheading class="mt-0.5">
                        {{ __('ولّد الأسابيع مرة واحدة، ثم املأها بالنسخ أو بمقدار سنوي أو بجدول جاهز.') }}
                    </flux:subheading>
                </div>
                <flux:icon :icon="$showYearTools ? 'chevron-up' : 'chevron-down'" class="size-5 text-zinc-400 shrink-0" />
            </button>

            @if ($showYearTools)
                <div class="mt-5 space-y-6 border-t border-zinc-100 dark:border-zinc-800 pt-5">

                    {{-- ١. توليد الأسابيع --}}
                    <div>
                        <div class="text-sm font-bold text-zinc-900 dark:text-white mb-1">
                            {{ __('١. ولّد أسابيع السنة') }}
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
                            {{ __('تُؤخذ التواريخ من التقويم الأكاديمي، ويُتخطّى أي أسبوع لا دوام فيه.') }}
                        </p>
                        <div class="flex flex-wrap items-end gap-2">
                            <flux:field>
                                <flux:label class="text-xs">{{ __('يبدأ في') }}</flux:label>
                                <flux:input type="date" wire:model="yearStartsOn" />
                                <flux:error name="yearStartsOn" />
                            </flux:field>
                            <flux:field>
                                <flux:label class="text-xs">{{ __('عدد الأسابيع') }}</flux:label>
                                <flux:input type="number" min="1" max="60" class="w-28" wire:model="yearWeeks" />
                                <flux:error name="yearWeeks" />
                            </flux:field>
                            <flux:button variant="primary" wire:click="generateYear" class="mb-0.5" icon="calendar-days">
                                {{ __('ولّد') }}
                            </flux:button>
                        </div>
                    </div>

                    {{-- ٢. النسخ --}}
                    @if ($this->week)
                        <div class="border-t border-zinc-100 dark:border-zinc-800 pt-5">
                            <div class="text-sm font-bold text-zinc-900 dark:text-white mb-1">
                                {{ __('٢. انسخ الأسبوع المفتوح على بقية السنة') }}
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
                                {{ __('يكتب محتوى الأسبوع') }} {{ $this->week->week_number }}
                                {{ __('على كل أسبوع سواه، ثم تعدّل المختلف.') }}
                            </p>
                            <flux:button variant="filled" wire:click="copyAcrossYear" icon="document-duplicate">
                                {{ __('انسخ على') }} {{ max($this->weeks->count() - 1, 0) }} {{ __('أسبوعاً') }}
                            </flux:button>
                        </div>
                    @endif

                    {{-- ٣. المقدار السنوي --}}
                    <div class="border-t border-zinc-100 dark:border-zinc-800 pt-5">
                        <div class="text-sm font-bold text-zinc-900 dark:text-white mb-1">
                            {{ __('٣. وزّع مقداراً سنوياً') }}
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
                            {{ __('اكتب مجموع السنة لكل مجال، ويُقسم على الأسابيع — والباقي يُضاف لآخر أسبوع.') }}
                        </p>
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                            @foreach ($tracks as $track)
                                <flux:field wire:key="an-{{ $track->value }}">
                                    <flux:label class="text-xs">{{ $track->label() }}</flux:label>
                                    <flux:input type="number" step="1" min="0"
                                        wire:model="annual.{{ $track->value }}" placeholder="0" />
                                    <flux:error name="annual.{{ $track->value }}" />
                                </flux:field>
                            @endforeach
                        </div>
                        <flux:button variant="filled" wire:click="distributeYear" class="mt-3" icon="calculator">
                            {{ __('وزّع على') }} {{ $this->weeks->count() }} {{ __('أسبوعاً') }}
                        </flux:button>
                    </div>

                    {{-- ٤. استيراد جدول --}}
                    <div class="border-t border-zinc-100 dark:border-zinc-800 pt-5">
                        <div class="text-sm font-bold text-zinc-900 dark:text-white mb-1">
                            {{ __('٤. استورد جدولاً جاهزاً') }}
                        </div>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mb-3">
                            {{ __('صيغة CSV أو xlsx، بأعمدة: الأسبوع، المجال، المحتوى، المقدار، الوحدة.') }}
                        </p>
                        <div class="flex flex-wrap items-end gap-2">
                            <flux:field class="flex-1 min-w-56">
                                <flux:input type="file" wire:model="sheet" accept=".csv,.xlsx,.txt" />
                                <flux:error name="sheet" />
                            </flux:field>
                            <flux:button variant="primary" wire:click="importSheet" class="mb-0.5" icon="arrow-up-tray">
                                {{ __('استورد') }}
                            </flux:button>
                            <flux:button variant="ghost" wire:click="downloadTemplate" class="mb-0.5" icon="arrow-down-tray">
                                {{ __('نموذج فارغ') }}
                            </flux:button>
                        </div>

                        @if ($importErrors !== [])
                            <div class="mt-3 rounded-xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/20 p-3">
                                <div class="text-xs font-bold text-amber-700 dark:text-amber-400 mb-1.5">
                                    {{ __('لم تُقرأ هذه الأسطر') }}
                                </div>
                                <ul class="text-xs text-amber-700 dark:text-amber-300 space-y-1 list-disc ps-4">
                                    @foreach (array_slice($importErrors, 0, 12) as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                                @if (count($importErrors) > 12)
                                    <div class="text-xs text-amber-600 dark:text-amber-400 mt-1.5">
                                        {{ __('و') }} {{ count($importErrors) - 12 }} {{ __('غيرها.') }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </flux:card>

        @if ($this->weeks->isNotEmpty())
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3 text-[11px] text-zinc-400">
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-emerald-500"></span>{{ __('مكتوب') }}</span>
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-amber-400"></span>{{ __('بعضه') }}</span>
                        <span class="flex items-center gap-1.5"><span class="size-2 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>{{ __('فارغ') }}</span>
                    </div>

                    @if ($this->currentWeekId)
                        <flux:button size="xs" variant="ghost" icon="calendar-days"
                            wire:click="openWeek({{ $this->currentWeekId }})">
                            {{ __('أسبوع اليوم') }}
                        </flux:button>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @foreach ($this->weeks as $item)
                        @php
                            $state = $this->weekState[$item->id] ?? ['filled' => 0, 'of' => 0, 'days' => 0];
                            $dot = $state['filled'] === 0
                                ? 'bg-zinc-300 dark:bg-zinc-700'
                                : ($state['filled'] === $state['of'] ? 'bg-emerald-500' : 'bg-amber-400');
                        @endphp
                        <button wire:key="week-{{ $item->id }}" wire:click="openWeek({{ $item->id }})"
                            title="{{ __(':a من :b مجالات · :d يوماً مكتوباً', ['a' => $state['filled'], 'b' => $state['of'], 'd' => $state['days']]) }}"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-bold border transition-colors
                                {{ $item->id === $weekId
                                    ? 'bg-maroon text-white border-maroon'
                                    : 'border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:border-maroon' }}
                                {{ $item->id === $this->currentWeekId && $item->id !== $weekId ? 'ring-1 ring-maroon/40' : '' }}">
                            <span class="size-2 rounded-full {{ $item->id === $weekId ? 'bg-white/70' : $dot }}"></span>
                            {{ $item->week_number }}
                            @if ($state['days'] > 0)
                                <span class="text-[10px] font-normal {{ $item->id === $weekId ? 'text-white/60' : 'text-zinc-400' }}">
                                    {{ $state['days'] }}<span class="align-super">·</span>
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($this->week)
            <flux:card>
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-zinc-100 dark:border-zinc-800">
                    <div>
                        <flux:heading size="lg">{{ __('الأسبوع') }} {{ $this->week->week_number }}</flux:heading>
                        <flux:subheading class="mt-0.5">
                            <x-hijri-date :date="$this->week->starts_on" /> — <x-hijri-date :date="$this->week->ends_on" />
                        </flux:subheading>
                    </div>
                    <flux:button variant="primary" wire:click="save" icon="check">{{ __('حفظ') }}</flux:button>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($tracks as $track)
                        <div wire:key="row-{{ $track->value }}" class="py-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                            <div class="md:col-span-3 flex items-center gap-2.5 pt-1">
                                <div class="p-2 rounded-xl bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300">
                                    <flux:icon :icon="$track->icon()" class="size-5" />
                                </div>
                                <span class="font-bold text-zinc-900 dark:text-white">{{ $track->label() }}</span>
                            </div>

                            <div class="md:col-span-5">
                                <flux:input wire:model="rows.{{ $track->value }}.description"
                                    placeholder="{{ __('المحتوى، مثل: سورة الملك') }}" />
                                <flux:error name="rows.{{ $track->value }}.description" />

                                @unless ($track->isQuranWird())
                                    <flux:input class="mt-2" type="url" dir="ltr"
                                        wire:model="rows.{{ $track->value }}.content_url"
                                        placeholder="{{ __('رابط المحتوى نفسه (اختياري)') }}" />
                                    <flux:error name="rows.{{ $track->value }}.content_url" />
                                @endunless
                            </div>

                            @php
                                $unit = $track->unitFor($rows[$track->value]['unit'] ?? null);
                                $wrongUnit = $rows[$track->value]['was_written_in'] ?? null;
                            @endphp

                            <div class="md:col-span-2">
                                @if (SelfProgramUnit::isDuration($unit))
                                    {{-- Time is asked for as it is spoken. --}}
                                    <div class="flex items-center gap-1.5">
                                        <flux:input type="number" min="0" max="99" class="text-center"
                                            wire:model="rows.{{ $track->value }}.hours"
                                            placeholder="{{ __('ساعة') }}" />
                                        <span class="text-zinc-400 text-xs shrink-0">:</span>
                                        <flux:input type="number" min="0" max="59" step="5" class="text-center"
                                            wire:model="rows.{{ $track->value }}.minutes"
                                            placeholder="{{ __('دقيقة') }}" />
                                    </div>
                                    <flux:error name="rows.{{ $track->value }}.hours" />
                                    <flux:error name="rows.{{ $track->value }}.minutes" />
                                @else
                                    <flux:input type="number" min="0"
                                        step="{{ SelfProgramUnit::step($unit) }}"
                                        wire:model="rows.{{ $track->value }}.target_amount"
                                        placeholder="{{ __('المقدار') }}" />
                                    <flux:error name="rows.{{ $track->value }}.target_amount" />
                                @endif
                            </div>

                            <div class="md:col-span-2">
                                @if ($track->choosesUnit())
                                    <flux:select wire:model.live="rows.{{ $track->value }}.unit">
                                        @foreach ($track->unitOptions() as $option => $meaning)
                                            <flux:select.option value="{{ $option }}">{{ $meaning }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="rows.{{ $track->value }}.unit" />
                                @elseif ($track->unitOptions() !== [])
                                    <flux:input value="{{ $unit }}" disabled />
                                @else
                                    <flux:input wire:model="rows.{{ $track->value }}.unit"
                                        placeholder="{{ __('الوحدة') }}" />
                                    <flux:error name="rows.{{ $track->value }}.unit" />
                                @endif

                                @if ($wrongUnit)
                                    <p class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">
                                        {{ __('كُتب سابقاً بـ«:unit»؛ راجع المقدار قبل الحفظ.', ['unit' => $wrongUnit]) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="text-xs text-zinc-500 dark:text-zinc-400 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    {{ __('مقدار صفر يعني أن المجال غير مطلوب هذا الأسبوع، فلا يُحسب على الطالب.') }}
                    {{ __('ووحدة الورد القرآني مثبّتة على الصفحة ليكتب فيها التسميع تلقائياً.') }}
                </p>
            </flux:card>

            {{-- الجدول اليومي --}}
            <flux:card class="mt-4 space-y-4">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <flux:heading size="lg">{{ __('الجدول اليومي') }}</flux:heading>
                        <flux:subheading class="mt-0.5">
                            {{ __('اكتب ما يخصّ كل يوم بعينه. واترك الخانة فارغة ليقسم النظام المقدار على الأيام كما يفعل اليوم.') }}
                        </flux:subheading>
                    </div>
                    <flux:button size="sm" variant="ghost"
                        wire:click="$toggle('showGrid')"
                        icon="{{ $showGrid ? 'chevron-up' : 'table-cells' }}">
                        {{ $showGrid ? __('إخفاء') : __('افتح الجدول') }}
                    </flux:button>
                </div>

                @if ($showGrid)
                    @php
                        $gridColumns = app(\App\Services\SelfProgramService::class)->dayColumns($this->week);
                    @endphp

                    <div class="flex items-center gap-2 flex-wrap text-xs">
                        <span class="text-zinc-500">{{ __('لدمج أيام في خانة واحدة: علّمها ثم اضغط ادمج.') }}</span>
                        <flux:button size="xs" variant="ghost" icon="arrows-pointing-in" wire:click="mergeSelected">
                            {{ __('ادمج المحدَّد') }}
                        </flux:button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr>
                                    <th class="p-2 text-right text-xs font-bold text-zinc-500 sticky start-0 bg-white dark:bg-zinc-900">
                                        {{ __('المجال') }}
                                    </th>
                                    @foreach ($gridColumns as $column)
                                        <th class="p-2 text-center text-xs font-bold text-zinc-500 min-w-40 align-top">
                                            @foreach ($column['days'] as $day)
                                                <div class="flex items-center justify-center gap-1">
                                                    @unless ($column['merged'])
                                                        <input type="checkbox" value="{{ $day }}" wire:model="selectedDays" class="accent-maroon" />
                                                    @endunless
                                                    <span>
                                                        <x-hijri-date :date="$day" style="weekdayOnly" />
                                                        <span class="text-[10px] font-normal text-zinc-400" dir="ltr">{{ substr($day, 5) }}</span>
                                                    </span>
                                                </div>
                                            @endforeach

                                            @if ($column['merged'])
                                                <button type="button" wire:click="unmerge('{{ $column['key'] }}')"
                                                    class="mt-1 text-[10px] font-normal text-maroon hover:underline">
                                                    {{ __('فكّ الدمج') }}
                                                </button>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tracks as $track)
                                    <tr class="border-t border-zinc-100 dark:border-zinc-800" wire:key="grid-{{ $track->key }}">
                                        @php
                                            $rowUnit = $track->unitFor($rows[$track->value]['unit'] ?? null);
                                            $inMinutes = SelfProgramUnit::isDuration($rowUnit);
                                        @endphp
                                        <td class="p-2 font-bold text-zinc-800 dark:text-zinc-100 whitespace-nowrap sticky start-0 bg-white dark:bg-zinc-900">
                                            {{ $track->label() }}
                                            <span class="block text-[10px] font-normal text-zinc-400">
                                                {{ $inMinutes ? __('بالدقائق') : $rowUnit }}
                                            </span>
                                        </td>
                                        @foreach ($gridColumns as $column)
                                            @php
                                                $day = $column['key'];
                                            @endphp
                                            <td class="p-1.5 align-top {{ $column['merged'] ? 'bg-zinc-50/70 dark:bg-zinc-800/30' : '' }}"
                                                wire:key="cell-{{ $track->key }}-{{ $day }}">
                                                <flux:input size="sm"
                                                    wire:model="grid.{{ $track->key }}.{{ $day }}.content"
                                                    placeholder="{{ __('المحتوى') }}" />
                                                <flux:input size="sm" class="mt-1" type="number" min="0"
                                                    step="{{ $inMinutes ? 5 : SelfProgramUnit::step($rowUnit) }}"
                                                    wire:model="grid.{{ $track->key }}.{{ $day }}.amount"
                                                    placeholder="{{ $inMinutes ? __('دقيقة') : __('المقدار') }}" />
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center gap-3 flex-wrap">
                        <flux:button variant="primary" icon="check" wire:click="saveGrid">{{ __('حفظ الجدول') }}</flux:button>
                        <flux:button variant="ghost" icon="eye" wire:click="$toggle('showPreview')">
                            {{ $showPreview ? __('إخفاء المعاينة') : __('كما يراه الطالب') }}
                        </flux:button>
                        <flux:text class="text-xs text-zinc-400">
                            {{ $this->writesForCohort()
                                ? __('تكتب لدفعتك، ويتقدّم ما تكتبه على جدول البرنامج.')
                                : __('تكتب لكل من يقرأ هذا الأسبوع، ولمعلّم الدفعة أن يخصّص فوقه.') }}
                        </flux:text>
                    </div>

                    {{-- اللصق من ورقة --}}
                    <div class="rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700 p-3 space-y-2">
                        <div class="text-xs font-bold text-zinc-600 dark:text-zinc-300">{{ __('الصق جدولاً من ملفك') }}</div>
                        <flux:textarea rows="3" wire:model="pasted" class="font-mono text-xs" dir="rtl"
                            placeholder="{{ __('انسخ الجدول من Word أو Excel والصقه هنا. أول خانة في السطر اسم المجال، وما بعدها أيام الأسبوع بالترتيب.') }}" />
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" variant="ghost" icon="clipboard-document" wire:click="applyPaste">
                                {{ __('وزّعه على الجدول') }}
                            </flux:button>
                            <flux:text class="text-[11px] text-zinc-400">{{ __('يملأ الخانات ولا يحفظ — راجعها ثم احفظ.') }}</flux:text>
                        </div>
                    </div>

                    {{-- المعاينة --}}
                    @if ($showPreview)
                        @php
                            $preview = app(\App\Services\SelfProgramService::class)
                                ->plannedGrid($this->week, $this->writesForCohort() ? $circleId : null);
                        @endphp

                        <div class="rounded-xl border border-maroon/20 bg-maroon/[0.03] dark:bg-maroon/[0.06] p-3 space-y-2">
                            <div class="text-xs font-bold text-maroon dark:text-red-secondary">
                                {{ __('كما يصل الطالب — بما فيه الأيام التي تركتها للحساب') }}
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr>
                                            <th class="p-1.5 text-right text-zinc-500">{{ __('المجال') }}</th>
                                            @foreach ($preview['columns'] as $column)
                                                <th class="p-1.5 text-center text-zinc-500 min-w-28">
                                                    <x-hijri-date :date="$column['key']" style="weekdayOnly" />
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($preview['rows'] as $row)
                                            <tr class="border-t border-zinc-100 dark:border-zinc-800" wire:key="pv-{{ $row['track']?->key }}">
                                                <td class="p-1.5 font-bold whitespace-nowrap">{{ $row['track']?->label() }}</td>
                                                @foreach ($preview['columns'] as $column)
                                                    @php
                                                        $cell = $row['cells'][$column['key']];
                                                    @endphp
                                                    <td class="p-1.5 text-center align-top {{ $cell['written'] ? '' : 'text-zinc-400' }}">
                                                        @if ($cell['content'])
                                                            <div class="leading-snug">{{ $cell['content'] }}</div>
                                                        @endif
                                                        @if ($cell['expected'] > 0)
                                                            <div class="tabular-nums text-[11px]">
                                                                {{ rtrim(rtrim(number_format($cell['expected'], 2, '.', ''), '0'), '.') }}
                                                                {{ $row['unit'] }}
                                                            </div>
                                                        @else
                                                            <span class="text-zinc-300 dark:text-zinc-700">—</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-[11px] text-zinc-400">
                                {{ __('الباهت مقدارٌ حسبه النظام، والواضح ما كتبته أنت.') }}
                            </div>
                        </div>
                    @endif
                @endif
            </flux:card>
        @endif
    @endif
</div>
