<?php

namespace App\Services\Reports;

/**
 * One report in the catalogue.
 *
 * A report says what it measures and how to gather it; it never says who may
 * read it or how much of the academy they see. The first is a permission, set
 * from the administrator's screen like any page; the second is `Scope`, which
 * the query carries. Keeping all three apart is what lets one report serve
 * every role instead of being written once per role.
 */
interface Report
{
    public function key(): string;

    public function label(): string;

    /** One line saying what the report answers. */
    public function description(): string;

    public function run(ReportQuery $query): ReportResult;

    /**
     * The ways this report may be gathered, keyed by the value the query takes.
     *
     * Most reports read students, so they offer the same four. A report whose
     * subject is not the student offers its own axes instead — and the screen
     * shows whatever it names, so a reader is never asked to gather tasks by
     * cohort.
     *
     * @return array<string, string>
     */
    public function groupings(): array;
}
