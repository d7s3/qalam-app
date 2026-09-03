<?php

namespace App\Services\Reports;

/**
 * A report's answer, in a shape every output can read.
 *
 * Reports differ in what they measure and not in how they are handed over, so
 * they all answer in these three parts — columns, rows, a total row. One CSV
 * writer and one printed sheet then serve every report there will ever be,
 * rather than each report growing its own pair.
 */
class ReportResult
{
    /**
     * @param  array<int, array{key: string, label: string, numeric?: bool}>  $columns
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     */
    public function __construct(
        public readonly string $title,
        public readonly array $columns,
        public readonly array $rows,
        public readonly array $totals = [],
        public readonly ?string $subtitle = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return array_column($this->columns, 'label');
    }

    /**
     * A row's cells in column order, as strings.
     *
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    public function cells(array $row): array
    {
        return array_map(
            fn (array $column) => (string) ($row[$column['key']] ?? ''),
            $this->columns,
        );
    }
}
