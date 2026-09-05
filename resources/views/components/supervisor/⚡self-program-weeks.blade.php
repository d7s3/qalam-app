<?php

use App\Models\SelfProgramWeek;
use App\Services\SelfProgramYearBuilder;
use App\Models\Stage;
use App\Support\SelfProgramSheet;
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

    public function mount(): void
    {
        $this->stageId = $this->stages->first()?->id;
        $this->newStartsOn = Carbon::today()->startOfWeek(Carbon::SUNDAY)->toDateString();
        $this->yearStartsOn = $this->newStartsOn;
        $this->openLatestWeek();
    }

    /**
     * The stages this supervisor speaks for. A supervisor writes the programme
     * for his own stages and no others.
     */
    /** @return Collection<int, Stage> */
    #[Computed]
    public function stages(): Collection
    {
        return Auth::guard('supervisor')->user()->stages()->orderBy('name')->get();
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

            $this->rows[$track->value] = [
                'description' => $item?->description ?? '',
                'content_url' => $item?->content_url ?? '',
                'target_amount' => $item ? (float) $item->target_amount : 0,
                'unit' => $track->fixedUnit() ?? ($item?->unit ?: $track->defaultUnit()),
            ];
        }
    }

    /**
     * Add the week that follows the last one, seven days after it.
     */
    public function addWeek(): void
    {
        $this->validate([
            'stageId' => ['required'],
            'newStartsOn' => ['required', 'date'],
        ], [], ['newStartsOn' => 'تاريخ البداية']);

        abort_unless($this->stages->contains('id', $this->stageId), 403);

        $starts = Carbon::parse($this->newStartsOn)->startOfDay();

        $week = SelfProgramWeek::create([
            'stage_id' => $this->stageId,
            'program_type' => SelfProgramWeek::TYPE_SELF,
            'week_number' => ($this->weeks->max('week_number') ?? 0) + 1,
            'starts_on' => $starts,
            'ends_on' => $starts->copy()->addDays(6),
            'created_by_id' => Auth::guard('supervisor')->id(),
            'created_by_type' => Auth::guard('supervisor')->user()::class,
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
            'rows.*.content_url' => ['nullable', 'url', 'max:2048'],
        ]);

        foreach (SelfProgramTrack::ordered() as $track) {
            $row = $this->rows[$track->value] ?? null;

            if (! $row) {
                continue;
            }

            $week->items()->updateOrCreate(
                ['track' => $track->value],
                [
                    'description' => $row['description'] ?: null,
                    // The Quran is in the application already; a link out of it
                    // would be a step backwards, so the wird never carries one.
                    'content_url' => $track->isQuranWird()
                        ? null
                        : (($row['content_url'] ?? '') ?: null),
                    'target_amount' => (float) ($row['target_amount'] ?: 0),
                    // The wird is measured in pages and nothing else, so the
                    // recitation bridge can write into it.
                    'unit' => $track->fixedUnit() ?? ($row['unit'] ?: $track->defaultUnit()),
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
            null,
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
            <div class="flex flex-wrap gap-2">
                @foreach ($this->weeks as $item)
                    <flux:button wire:key="week-{{ $item->id }}" size="sm"
                        :variant="$item->id === $weekId ? 'primary' : 'filled'"
                        wire:click="openWeek({{ $item->id }})">
                        {{ __('الأسبوع') }} {{ $item->week_number }}
                    </flux:button>
                @endforeach
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

                            <div class="md:col-span-2">
                                <flux:input type="number" step="0.25" min="0"
                                    wire:model="rows.{{ $track->value }}.target_amount"
                                    placeholder="{{ __('المقدار') }}" />
                                <flux:error name="rows.{{ $track->value }}.target_amount" />
                            </div>

                            <div class="md:col-span-2">
                                @if ($track->fixedUnit())
                                    <flux:input value="{{ $track->fixedUnit() }}" disabled />
                                @else
                                    <flux:input wire:model="rows.{{ $track->value }}.unit"
                                        placeholder="{{ __('الوحدة') }}" />
                                    <flux:error name="rows.{{ $track->value }}.unit" />
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
        @endif
    @endif
</div>
