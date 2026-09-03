<?php

use App\Services\Reports\ReportCatalogue;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportQuery;
use App\Services\Reports\ReportResult;
use App\Support\HijriDate;
use App\Support\Scope;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** Captured at mount: a Livewire update names no role of its own. */
    public string $role = '';

    public string $reportKey = '';

    public string $groupBy = ReportQuery::BY_STUDENT;

    public string $subjectType = '';

    public ?int $subjectId = null;

    public string $from = '';

    public string $to = '';

    public function mount(?string $report = null): void
    {
        $this->role = Scope::resolveRole();

        // Opens on the Hijri month running now, since that is the month the
        // academy reckons in.
        $grid = HijriDate::monthGrid();
        $this->from = $grid['first'];
        $this->to = min($grid['last'], now()->toDateString());

        // Opened straight onto a report when the link named one.
        $this->reportKey = $report ?? ($this->available[0]?->key() ?? '');
    }

    #[Computed]
    public function scope(): Scope
    {
        return Scope::forRole($this->role);
    }

    /** @return array<int, \App\Services\Reports\Report> */
    #[Computed]
    public function available(): array
    {
        return ReportCatalogue::for($this->scope);
    }

    #[Computed]
    public function query(): ReportQuery
    {
        return new ReportQuery(
            scope: $this->scope,
            from: Carbon::parse($this->from)->startOfDay(),
            to: Carbon::parse($this->to)->endOfDay(),
            groupBy: $this->groupBy,
            subjectType: $this->subjectType ?: null,
            subjectId: $this->subjectType ? $this->subjectId : null,
        );
    }

    /**
     * The axes the chosen report offers — not always the student's four. A
     * report about tasks is gathered by the person or the kind of work, and
     * asking to gather it by cohort would mean nothing.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function groupings(): array
    {
        return ReportCatalogue::find($this->reportKey)?->groupings() ?? ReportQuery::groupings();
    }

    #[Computed]
    public function choices(): array
    {
        return $this->query->subjectChoices();
    }

    #[Computed]
    public function result(): ?ReportResult
    {
        $report = ReportCatalogue::find($this->reportKey);

        // Compared by key, not by identity: the catalogue resolves a fresh
        // instance each time it is asked, so two objects for the same report
        // are never the same object.
        $open = array_map(fn ($available) => $available->key(), $this->available);

        if (! $report || ! in_array($report->key(), $open, true)) {
            return null;
        }

        return $report->run($this->query);
    }

    /**
     * Jump the period to a whole Hijri month, since that is how the academy
     * speaks of a period.
     */
    public function shiftMonth(int $months): void
    {
        $current = HijriDate::yearMonthOf($this->from);
        $moved = HijriDate::shiftMonth($current['year'], $current['month'], $months);
        $grid = HijriDate::monthGrid($moved['year'], $moved['month']);

        $this->from = $grid['first'];
        $this->to = $grid['last'];
    }

    /**
     * Choosing a report resets the axis, since the one in hand may not be among
     * those the new report offers.
     */
    public function chooseReport(string $key): void
    {
        $this->reportKey = $key;

        unset($this->groupings, $this->result);

        $this->groupBy = array_key_first($this->groupings()) ?? ReportQuery::BY_STUDENT;
    }

    public function updatedSubjectType(): void
    {
        $this->subjectId = null;
    }

    public function exportCsv()
    {
        return $this->export(fn (ReportResult $result) => ReportExporter::csv($result));
    }

    public function exportPdf()
    {
        return $this->export(fn (ReportResult $result) => ReportExporter::pdf($result));
    }

    private function export(callable $handover)
    {
        $result = $this->result;

        // The same permission that draws the table governs the file: a report
        // withheld on screen must not be reachable by asking for its download.
        abort_unless($result instanceof ReportResult, 403);

        return $handover($result);
    }
};
?>

<div class="space-y-5" dir="rtl">
    @if ($this->available === [])
        <flux:card class="text-center py-12">
            <flux:icon icon="chart-bar-square" class="size-10 mx-auto text-zinc-300 dark:text-zinc-600" />
            <flux:heading size="lg" class="mt-3">{{ __('لا تقارير متاحة لك') }}</flux:heading>
            <flux:subheading class="mt-1">{{ __('تُفتح التقارير من شاشة صلاحيات المستخدمين.') }}</flux:subheading>
        </flux:card>
    @else
        {{-- اختيار التقرير --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($this->available as $report)
                <flux:button wire:key="r-{{ $report->key() }}" size="sm"
                    :variant="$report->key() === $reportKey ? 'primary' : 'filled'"
                    wire:click="chooseReport('{{ $report->key() }}')">
                    {{ $report->label() }}
                </flux:button>
            @endforeach
        </div>

        <flux:card>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <flux:field>
                    <flux:label>{{ __('التجميع') }}</flux:label>
                    <flux:select wire:model.live="groupBy">
                        @foreach ($this->groupings as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('يقتصر على') }}</flux:label>
                    <flux:select wire:model.live="subjectType">
                        <flux:select.option value="">{{ __('كل ما أبلغه') }}</flux:select.option>
                        <flux:select.option value="stage">{{ __('برنامج بعينه') }}</flux:select.option>
                        <flux:select.option value="circle">{{ __('دفعة بعينها') }}</flux:select.option>
                    </flux:select>
                </flux:field>

                @if ($subjectType)
                    <flux:field>
                        <flux:label>{{ $subjectType === 'stage' ? __('البرنامج') : __('الدفعة') }}</flux:label>
                        <flux:select wire:model.live="subjectId">
                            <flux:select.option value="">{{ __('اختر…') }}</flux:select.option>
                            @foreach ($subjectType === 'stage' ? $this->choices['stages'] : $this->choices['circles'] as $choice)
                                <flux:select.option wire:key="c-{{ $subjectType }}-{{ $choice->id }}" value="{{ $choice->id }}">
                                    {{ $choice->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                @endif

                <div class="flex items-end gap-2">
                    <flux:button size="sm" variant="filled" wire:click="shiftMonth(-1)" icon="chevron-right" />
                    <flux:button size="sm" variant="filled" wire:click="shiftMonth(1)" icon="chevron-left" />
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('شهر هجري') }}</span>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-zinc-100 dark:border-zinc-800 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                <flux:field>
                    <flux:label>{{ __('من') }}</flux:label>
                    <flux:input type="date" wire:model.live="from" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('إلى') }}</flux:label>
                    <flux:input type="date" wire:model.live="to" />
                </flux:field>
                <div class="flex gap-2">
                    <flux:button variant="filled" wire:click="exportCsv" icon="table-cells">{{ __('جدول') }}</flux:button>
                    <flux:button variant="filled" wire:click="exportPdf" icon="document-arrow-down">{{ __('ورقة') }}</flux:button>
                </div>
            </div>
        </flux:card>

        @if ($this->result)
            <flux:card class="p-0 overflow-hidden">
                <div class="p-5 border-b border-zinc-100 dark:border-zinc-800">
                    <flux:heading size="lg">{{ $this->result->title }}</flux:heading>
                    <flux:subheading class="mt-0.5">{{ $this->result->subtitle }}</flux:subheading>
                </div>

                @if ($this->result->isEmpty())
                    <p class="p-10 text-center text-sm text-zinc-400">{{ __('لا بيانات في هذه المدة.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <flux:table>
                            <flux:table.columns>
                                @foreach ($this->result->columns as $column)
                                    <flux:table.column>{{ $column['label'] }}</flux:table.column>
                                @endforeach
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->result->rows as $index => $row)
                                    <flux:table.row wire:key="row-{{ $index }}">
                                        @foreach ($this->result->columns as $column)
                                            <flux:table.cell class="{{ ($column['numeric'] ?? false) ? 'tabular-nums' : 'font-medium' }}">
                                                {{ $row[$column['key']] ?? '—' }}
                                            </flux:table.cell>
                                        @endforeach
                                    </flux:table.row>
                                @endforeach
                                @if ($this->result->totals !== [])
                                    <flux:table.row class="bg-zinc-50 dark:bg-zinc-800/50 font-bold">
                                        @foreach ($this->result->columns as $column)
                                            <flux:table.cell class="{{ ($column['numeric'] ?? false) ? 'tabular-nums' : '' }}">
                                                {{ $this->result->totals[$column['key']] ?? '' }}
                                            </flux:table.cell>
                                        @endforeach
                                    </flux:table.row>
                                @endif
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @endif
            </flux:card>
        @endif
    @endif
</div>
