<?php

use App\Ai\SelfProgramWeekReader;
use App\Models\AppNotification;
use App\Models\Circle;
use App\Models\SelfProgramItem;
use App\Models\SelfProgramTrack;
use App\Models\SelfProgramWeek;
use App\Models\Stage;
use App\Models\Supervisor;
use App\Models\UserRole;
use App\Services\SelfProgramService;
use App\Support\SelfProgramUnit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Asked for by Faisal, 2 Nov 2026, after a supervisor wrote week 7 believing
 * it was this week, left two fields without amounts, and تأصيل's weeks ran toward their end
 * with nobody told: the page opens on today's week, asks about a field with no
 * amount, reads the week out of a pasted message, and warns before a coming
 * week opens empty.
 */
beforeEach(function () {
    // A Monday inside the second week below.
    Carbon::setTestNow('2026-11-02 08:00:00');

    $this->stage = Stage::factory()->create(['name' => 'تأهيل']);
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

describe('the supervisor page', function () {
    beforeEach(function () {
        $this->before = ($this->week)(6, '2026-10-25');
        $this->now = ($this->week)(7, '2026-11-01');
        $this->later = ($this->week)(8, '2026-11-08');
    });

    it('opens on the week running today, not the last one', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->assertSet('weekId', $this->now->id)
            ->assertSee('هذا الأسبوع')
            ->call('stepWeek', 1)
            ->assertSet('weekId', $this->later->id)
            ->call('stepWeek', 1)
            ->assertSet('weekId', $this->later->id)
            ->call('stepWeek', -1)
            ->call('stepWeek', -1)
            ->assertSet('weekId', $this->before->id);
    });

    it('asks about a field given content and no amount before saving it', function () {
        $page = Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('rows.masmou.description', 'شرح تائية الألبيري')
            ->call('save')
            ->assertSet('missingAmounts', ['المسموع'])
            ->assertSee('لم تكتب مقدار: المسموع');

        expect(SelfProgramItem::where('self_program_week_id', $this->now->id)->where('track', 'masmou')->value('description'))
            ->toBeNull();

        $page->call('save', true)->assertSet('missingAmounts', []);

        expect(SelfProgramItem::where('self_program_week_id', $this->now->id)->where('track', 'masmou')->value('description'))
            ->toBe('شرح تائية الألبيري');
    });

    it('saves without asking once the amount is written', function () {
        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('rows.masmou.description', 'شرح تائية الألبيري')
            ->set('rows.masmou.minutes', 45)
            ->call('save')
            ->assertSet('missingAmounts', []);

        expect((float) SelfProgramItem::where('self_program_week_id', $this->now->id)->where('track', 'masmou')->value('target_amount'))
            ->toEqual(45.0);
    });

    it('reads a pasted message into the fields without saving it', function () {
        SelfProgramWeekReader::fake([json_encode([
            'maqrou' => ['description' => 'العواصم من القواصم', 'amount' => 50, 'unit' => 'صفحة'],
            'masmou' => ['description' => 'شرح تائية الألبيري', 'amount' => 90, 'unit' => 'دقيقة'],
        ])]);

        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('message', 'المقروء: العواصم من القواصم ٥٠ صفحة. المسموع: شرح تائية الألبيري ساعة ونصف.')
            ->call('readMessage')
            ->assertSet('rows.maqrou.description', 'العواصم من القواصم')
            ->assertSet('rows.maqrou.target_amount', 50.0)
            ->assertSet('rows.masmou.hours', 1)
            ->assertSet('rows.masmou.minutes', 30)
            ->assertSet('message', '');

        expect(SelfProgramItem::where('self_program_week_id', $this->now->id)->where('description', '!=', '')->count())->toBe(0);
    });
});

describe('reading a message', function () {
    it('keeps only the fields the programme has, in units they take', function () {
        $rows = SelfProgramWeekReader::sanitize("```json\n".json_encode([
            'maqrou' => ['description' => 'العواصم', 'amount' => '40', 'unit' => 'دقيقة'],
            'telepathy' => ['description' => 'تأمّل', 'amount' => 5, 'unit' => 'دقيقة'],
            'mahfoudh' => ['description' => '', 'amount' => 0, 'unit' => 'بيت'],
        ])."\n```");

        // المقروء is counted in pages whatever the reply says, and an empty field is no field.
        expect($rows)->toBe([
            'maqrou' => ['description' => 'العواصم', 'amount' => 40.0, 'unit' => 'صفحة'],
        ]);
    });

    it('gives nothing back for a reply that is not JSON', function () {
        expect(SelfProgramWeekReader::sanitize('لا أستطيع'))->toBe([]);
    });
});

describe('a coming week left empty', function () {
    it('is found when the next week has nothing written', function () {
        ($this->week)(7, '2026-11-01');
        ($this->week)(8, '2026-11-08');

        expect(app(SelfProgramService::class)->comingWeek($this->stage->id))
            ->toBe(['starts_on' => '2026-11-08', 'week_number' => 8, 'written' => false]);
    });

    it('is found when the weeks simply run out', function () {
        ($this->week)(7, '2026-11-01');

        expect(app(SelfProgramService::class)->comingWeek($this->stage->id))
            ->toBe(['starts_on' => '2026-11-08', 'week_number' => null, 'written' => false]);
    });

    it('is not asked of a programme with no self programme, or one long finished', function () {
        expect(app(SelfProgramService::class)->comingWeek($this->stage->id))->toBeNull();

        ($this->week)(1, '2026-09-06');

        expect(app(SelfProgramService::class)->comingWeek($this->stage->id))->toBeNull();
    });

    it('tells the programme\'s supervisors on Thursday, but not a sheikh narrowed to his cohort', function () {
        ($this->week)(7, '2026-11-01');

        $sheikh = Supervisor::factory()->create();
        $sheikh->stages()->attach($this->stage->id);
        $sheikh->roles()->where('role', 'supervisor')->update([
            'scope_type' => UserRole::SCOPE_CIRCLES,
            'scope_ids' => [Circle::factory()->create(['stage_id' => $this->stage->id])->id],
        ]);

        $this->artisan('self-program:remind-empty-weeks')->assertSuccessful();

        expect(AppNotification::where('type', 'self_program_week_empty')->pluck('recipient_id')->all())
            ->toBe([$this->supervisor->id]);
    });

    it('stays quiet once the coming week is written', function () {
        ($this->week)(7, '2026-11-01');
        SelfProgramItem::create([
            'self_program_week_id' => ($this->week)(8, '2026-11-08')->id,
            'track' => SelfProgramTrack::MAQROU,
            'description' => 'العواصم',
            'target_amount' => 40,
            'unit' => 'صفحة',
        ]);

        $this->artisan('self-program:remind-empty-weeks')->assertSuccessful();

        expect(AppNotification::where('type', 'self_program_week_empty')->count())->toBe(0);
    });

    it('is shown on the supervisor\'s home page', function () {
        ($this->week)(7, '2026-11-01');

        $this->actingAs($this->supervisor, 'supervisor')
            ->get(route('supervisor.dashboard'))
            ->assertOk()
            ->assertSee('الأسبوع القادم في تأهيل');
    });
});

// A supervisor's «‎204-‎» showed in the browser's number box and reached the
// platform empty, so the week would not save (8 Nov 2026). The boxes now take
// text and the amount is read as it was typed.
describe('amounts as typed', function () {
    it('reads Arabic digits, a pasted dash and a word', function ($typed, $read) {
        expect(SelfProgramUnit::parse($typed))->toBe($read);
    })->with([
        ['204', 204.0],
        ['204-', 204.0],
        ['- 204', 204.0],
        ['٢٠٤', 204.0],
        ['٦٫٥ صفحة', 6.5],
        ['كثير', null],
        ['', null],
    ]);

    it('saves the week when the supervisor typed a dash or Arabic digits', function () {
        ($this->week)(8, '2026-11-01');

        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('rows.maqrou.description', 'مقدمة الحوار')
            ->set('rows.maqrou.target_amount', '204-')
            ->set('rows.tahdheer.description', 'الدرر المكية')
            ->set('rows.tahdheer.hours', '١')
            ->set('rows.tahdheer.minutes', '٣٣')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('missingAmounts', []);

        expect(SelfProgramItem::where('track', 'maqrou')->value('target_amount'))->toEqual(204)
            ->and(SelfProgramItem::where('track', 'tahdheer')->value('target_amount'))->toEqual(93);
    });

    it('still refuses a box with no number in it', function () {
        ($this->week)(8, '2026-11-01');

        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('rows.maqrou.target_amount', 'كثير')
            ->call('save')
            ->assertHasErrors('rows.maqrou.target_amount');
    });
});

describe('after saving', function () {
    it('points at the box that needs an amount', function () {
        ($this->week)(8, '2026-11-01');

        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('rows.maqrou.description', 'مقدمة الحوار')
            ->set('rows.masmou.description', 'تائية الألبيري')
            ->call('save')
            ->assertHasErrors(['rows.maqrou.target_amount', 'rows.masmou.minutes']);
    });

    it('shows the amounts back as they were kept', function () {
        ($this->week)(8, '2026-11-01');

        Livewire::actingAs($this->supervisor, 'supervisor')
            ->test('supervisor.self-program-weeks')
            ->set('rows.maqrou.description', 'مقدمة الحوار')
            ->set('rows.maqrou.target_amount', '204-')
            ->set('rows.tahdheer.description', 'الدرر المكية')
            ->set('rows.tahdheer.unit', 'دقيقة')
            ->set('rows.tahdheer.minutes', '93')
            ->call('save')
            ->assertSet('rows.maqrou.target_amount', 204.0)
            ->assertSet('rows.tahdheer.hours', 1)
            ->assertSet('rows.tahdheer.minutes', 33);
    });
});
