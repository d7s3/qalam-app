<?php

use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Asked for by Faisal, 14 Nov 2026: under المقروء each book shows the pages
 * from and to, every week; and an arrear says what was asked, not only how
 * much of it is left.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-11-10 08:00:00');

    $this->stage = Stage::factory()->create();
    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->stage->id);

    $this->week = fn (int $number, string $starts) => SelfProgramWeek::create([
        'stage_id' => $this->stage->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => $number,
        'starts_on' => $starts,
        'ends_on' => Carbon::parse($starts)->addDays(6)->toDateString(),
    ]);
});

it('keeps each book with its pages and counts the pages as the amount', function () {
    $week = ($this->week)(9, '2026-11-08');

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->assertSet('weekId', $week->id)
        ->set('rows.maqrou.books.0.title', 'العواصم من القواصم')
        ->set('rows.maqrou.books.0.from', '٢٦٥')
        ->set('rows.maqrou.books.0.to', '395')
        ->call('addBook')
        ->set('rows.maqrou.books.1.title', 'مدخل لفهم السيرة')
        ->set('rows.maqrou.books.1.from', '1')
        ->set('rows.maqrou.books.1.to', '10')
        ->call('save')
        ->assertHasNoErrors();

    $item = SelfProgramItem::where('self_program_week_id', $week->id)->where('track', SelfProgramTrack::MAQROU)->sole();

    expect((float) $item->target_amount)->toEqual(141.0)
        ->and($item->description)->toBe('العواصم من القواصم + مدخل لفهم السيرة')
        ->and($item->bookRanges())->toBe(['العواصم من القواصم: من ص 265 إلى ص 395', 'مدخل لفهم السيرة: من ص 1 إلى ص 10']);
});

it('will not save reading without the page each book stops at', function () {
    $week = ($this->week)(9, '2026-11-08');

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('rows.maqrou.description', 'العواصم')
        ->set('rows.maqrou.books.0.title', 'العواصم')
        ->call('save')
        ->assertHasErrors('rows.maqrou.books');

    expect(SelfProgramItem::where('self_program_week_id', $week->id)->where('track', SelfProgramTrack::MAQROU)->value('description'))->toBeNull();
});

it('refuses a book that ends before it starts', function () {
    ($this->week)(9, '2026-11-08');

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('rows.maqrou.books.0.title', 'العواصم')
        ->set('rows.maqrou.books.0.from', '300')
        ->set('rows.maqrou.books.0.to', '200')
        ->call('save')
        ->assertHasErrors('rows.maqrou.books.0.to');
});

it('carries last week\'s books on from the page after they stopped', function () {
    $before = ($this->week)(8, '2026-11-01');
    ($this->week)(9, '2026-11-08');
    SelfProgramItem::create([
        'self_program_week_id' => $before->id,
        'track' => SelfProgramTrack::MAQROU,
        'description' => 'العواصم',
        'books' => [['title' => 'العواصم', 'from' => 120, 'to' => 264]],
        'target_amount' => 145,
        'unit' => 'صفحة',
    ]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->assertSee('أكمل كتب الأسبوع الماضي')
        ->call('carryBooks')
        ->assertSet('rows.maqrou.books', [['title' => 'العواصم', 'from' => 265, 'to' => '']]);
});

it('shows the student each book\'s pages, and what an arrear asked for', function () {
    $circle = Circle::factory()->create(['stage_id' => $this->stage->id]);
    $student = Student::factory()->create(['circle_id' => $circle->id]);
    $before = ($this->week)(8, '2026-11-01');
    $now = ($this->week)(9, '2026-11-08');

    foreach ([$before, $now] as $week) {
        SelfProgramItem::create([
            'self_program_week_id' => $week->id,
            'track' => SelfProgramTrack::MAQROU,
            'description' => 'العواصم',
            'books' => [['title' => 'العواصم', 'from' => $week->is($before) ? 120 : 265, 'to' => $week->is($before) ? 264 : 395]],
            'target_amount' => 145,
            'unit' => 'صفحة',
        ]);
    }

    Livewire::actingAs($student, 'student')
        ->test('student.self-program')
        ->assertSee('العواصم: من ص 265 إلى ص 395')
        ->assertSee('المتأخرات')
        ->assertSee('المطلوب: العواصم: من ص 120 إلى ص 264');
});

it('works out where each book starts from last week, or from its first page', function () {
    $before = ($this->week)(8, '2026-11-01');
    $week = ($this->week)(9, '2026-11-08');
    SelfProgramItem::create([
        'self_program_week_id' => $before->id,
        'track' => SelfProgramTrack::MAQROU,
        'books' => [['title' => 'العواصم', 'from' => 120, 'to' => 264]],
        'target_amount' => 145,
        'unit' => 'صفحة',
    ]);

    Livewire::actingAs($this->supervisor, 'supervisor')
        ->test('supervisor.self-program-weeks')
        ->set('rows.maqrou.books.0.title', 'العواصم')
        ->set('rows.maqrou.books.0.to', '395')
        ->call('addBook')
        ->set('rows.maqrou.books.1.title', 'مدخل لفهم السيرة')
        ->set('rows.maqrou.books.1.to', '٩')
        ->call('save')
        ->assertHasNoErrors();

    $item = SelfProgramItem::where('self_program_week_id', $week->id)->where('track', SelfProgramTrack::MAQROU)->sole();

    expect($item->bookRanges())->toBe(['العواصم: من ص 265 إلى ص 395', 'مدخل لفهم السيرة: من ص 1 إلى ص 9'])
        ->and((float) $item->target_amount)->toEqual(140.0);
});
