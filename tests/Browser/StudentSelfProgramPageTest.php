<?php

use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Student;

/**
 * What the student actually sees, measured on a page a browser has laid out.
 *
 * This suite exists because of one mistake, and the mistake was mine. The self
 * programme's grid shows «done / target», and reading the markup gives «10 /
 * 13.5» while the screen puts 13.5 to the left of 10. I read the screen
 * left-to-right, called it reversed, and forced each pair into a left-to-right
 * island — which moved the target to where an Arabic reader looks first, and
 * broke a display that had been correct for as long as it had existed.
 *
 * The lesson is not «open a browser». It is that `innerText` and `assertSee`
 * answer in markup order whatever the screen does, so a test written with them
 * cannot see this class of thing at all — the first browser test written here
 * passed with the bug reintroduced.
 *
 * So these measure pixels. A `Range` over each number gives where it truly sits,
 * and the rule is the one an Arabic reader lives by: what he meets first, coming
 * from the right, is what he was meant to read first.
 */
beforeEach(function () {
    $programme = Stage::factory()->create();
    $cohort = Circle::factory()->create(['stage_id' => $programme->id]);

    $this->student = Student::factory()->create([
        'circle_id' => $cohort->id,
        'stage_id' => $programme->id,
        'is_approved' => true,
    ]);

    $week = SelfProgramWeek::create([
        'stage_id' => $programme->id,
        'program_type' => SelfProgramWeek::TYPE_SELF,
        'week_number' => 1,
        'starts_on' => now('Asia/Riyadh')->startOfWeek()->format('Y-m-d'),
        'ends_on' => now('Asia/Riyadh')->startOfWeek()->addDays(6)->format('Y-m-d'),
    ]);

    SelfProgramItem::create([
        'self_program_week_id' => $week->id,
        'track' => SelfProgramTrack::MAHFOUDH,
        'description' => 'شواهد المتممة',
        'target_amount' => 27,
        'unit' => 'بيت',
    ]);
});

it('puts what he has done where an Arabic reader looks first', function () {
    $this->actingAs($this->student, 'student');

    $page = visit('/student/self-program');

    $page->assertNoJavaScriptErrors();

    $cell = $page->script(<<<'JS'
        (() => {
            const td = [...document.querySelectorAll('table td')]
                .find(td => /^\d+(\.\d+)?\s*\/\s*\d/.test(td.innerText.trim()));

            if (! td) { return null; }

            const walker = document.createTreeWalker(td, NodeFilter.SHOW_TEXT);
            let node = null;

            while (walker.nextNode()) {
                if (/\d\s*\/\s*\d/.test(walker.currentNode.textContent)) {
                    node = walker.currentNode;
                    break;
                }
            }

            if (! node) { return null; }

            const text = node.textContent;
            const slash = text.indexOf('/');
            const leftOf = (from, to) => {
                const range = document.createRange();
                range.setStart(node, from);
                range.setEnd(node, to);
                return range.getBoundingClientRect().left;
            };

            return {
                markup: text.trim().replace(/\s+/g, ' '),
                doneLeft: leftOf(0, slash),
                targetLeft: leftOf(slash + 1, text.length),
            };
        })()
    JS);

    expect($cell)->not->toBeNull('لا خانة في الجدول — البذرة لم تصل الصفحة');

    // Nothing is done yet and the target is twenty-seven, so the markup must
    // read «0 / something» — and the zero must sit to the RIGHT of it, because
    // that is where the eye starts.
    expect($cell['doneLeft'])->toBeGreaterThan(
        $cell['targetLeft'],
        "خانة «{$cell['markup']}»: المنجَز يجب أن يكون على يمين المطلوب ليقرأه العربي أوّلاً",
    );
});

it('never makes the page itself scroll sideways, on a phone', function () {
    $this->actingAs($this->student, 'student');

    $page = visit('/student/self-program')->on()->mobile();

    $page->assertNoJavaScriptErrors();

    // A wide table may scroll inside its own box; the page beneath it may not,
    // or every screen on the phone drifts and nothing lines up again.
    $overflow = $page->script('document.documentElement.scrollWidth - document.documentElement.clientWidth');

    expect($overflow)->toBeLessThanOrEqual(1, 'الصفحة نفسها تنساح أفقياً على الجوّال');
});
