<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\SelfProgramWeek;
use App\Models\Student;
use App\Models\StudentEvent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Asked for by Faisal, 6 Nov 2026: record how students use the platform so
 * trouble shows before a student gives up — and only where they went and what
 * went wrong, never what they typed.
 */
beforeEach(function () {
    $this->student = Student::factory()->create([
        'name' => 'عمر محمد',
        'circle_id' => Circle::factory()->create()->id,
    ]);
});

describe('recording', function () {
    it('notes each page a student opens', function () {
        $this->actingAs($this->student, 'student')->get(route('student.dashboard'))->assertOk();

        expect(StudentEvent::where('student_id', $this->student->id)->where('kind', StudentEvent::PAGE)->value('route_name'))
            ->toBe('student.dashboard');
    });

    it('notes a sign-in and a failed one, for students only', function () {
        event(new Login('student', $this->student, false));
        event(new Failed('student', $this->student, ['email' => $this->student->email]));
        event(new Login('manager', Manager::factory()->create(), false));

        expect(StudentEvent::pluck('kind')->all())->toBe([StudentEvent::LOGIN, StudentEvent::LOGIN_FAILED]);
    });

    it('notes an empty self programme once a day, however often the page is drawn', function () {
        $page = Livewire::actingAs($this->student, 'student')->test('student.self-program');
        $page->call('$refresh');

        expect(StudentEvent::where('kind', StudentEvent::SIGNAL)->pluck('detail')->all())->toBe(['self_program_no_week']);
    });

    it('notes a week written with nothing to record in it', function () {
        SelfProgramWeek::create([
            'stage_id' => $this->student->circle->stage_id,
            'program_type' => SelfProgramWeek::TYPE_SELF,
            'week_number' => 1,
            'starts_on' => now()->startOfWeek()->toDateString(),
            'ends_on' => now()->startOfWeek()->addDays(6)->toDateString(),
        ]);

        Livewire::actingAs($this->student, 'student')->test('student.self-program');

        expect(StudentEvent::where('kind', StudentEvent::SIGNAL)->value('detail'))->toBe('self_program_empty_week');
    });

    it('lets go of what is older than ninety days', function () {
        StudentEvent::factory()->create(['student_id' => $this->student->id, 'created_at' => now()->subDays(91)]);
        StudentEvent::factory()->create(['student_id' => $this->student->id, 'created_at' => now()->subDays(10)]);

        $this->artisan('model:prune', ['--model' => [StudentEvent::class]]);

        expect(StudentEvent::count())->toBe(1);
    });
});

describe('the manager page', function () {
    it('shows who needs following up, and why', function () {
        $away = Student::factory()->create(['name' => 'خالد سعيد', 'circle_id' => $this->student->circle_id]);
        StudentEvent::factory()->create(['student_id' => $away->id, 'kind' => StudentEvent::LOGIN, 'created_at' => now()->subDays(20)]);

        StudentEvent::factory()->create(['student_id' => $this->student->id, 'kind' => StudentEvent::LOGIN]);
        StudentEvent::factory()->count(3)->create(['student_id' => $this->student->id, 'kind' => StudentEvent::LOGIN_FAILED, 'route_name' => null]);

        Livewire::actingAs(Manager::factory()->create(), 'manager')
            ->test('manager.student-behavior')
            ->assertSee('عمر محمد')
            ->assertSee('أخطأ في كلمة المرور 3 مرات')
            ->assertSee('دخل ولم يسجّل إنجازاً في البرنامج الذاتي')
            ->assertSee('خالد سعيد')
            ->assertSee('لم يدخل منذ 20 يوماً');
    });

    it('opens for the manager', function () {
        $this->actingAs(Manager::factory()->create(), 'manager')
            ->get(route('manager.student-behavior'))
            ->assertOk()
            ->assertSee('سلوك الطلاب');
    });
});

// Asked for by Faisal, 9 Nov 2026: what students do inside the pages, how long
// they stay, and on what device.
describe('what they do and how long they stay', function () {
    it('notes the device a student signs in on', function () {
        app('request')->headers->set('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Mobile/15E148');

        event(new Login('student', $this->student, false));

        expect(StudentEvent::where('kind', StudentEvent::LOGIN)->value('detail'))->toBe('جوال')
            ->and(StudentEvent::device('Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0)'))->toBe('حاسب')
            ->and(StudentEvent::device('Mozilla/5.0 (iPad; CPU OS 17_0)'))->toBe('جهاز لوحي');
    });

    it('notes a book added in his library', function () {
        Livewire::actingAs($this->student, 'student')
            ->test('student.library')
            ->set('title', 'العواصم من القواصم')
            ->call('addBook');

        expect(StudentEvent::where('kind', StudentEvent::ACTION)->value('detail'))->toBe('library_book_added');
    });

    it('reads time spent as visits split by half an hour of quiet', function () {
        $at = fn (string $time) => (object) ['created_at' => Carbon\Carbon::parse("2026-11-09 {$time}")];

        $spent = Livewire::new('manager.student-behavior')::timeSpent(
            collect([$at('10:00'), $at('10:12'), $at('10:20'), $at('18:00'), $at('18:05')]),
        );

        expect($spent)->toBe(['minutes' => 25, 'visits' => 2]);
    });

    it('shows the manager what students did, and who opened the self programme without recording', function () {
        StudentEvent::factory()->create(['student_id' => $this->student->id, 'kind' => StudentEvent::LOGIN, 'route_name' => null, 'detail' => 'جوال']);
        StudentEvent::factory()->count(2)->create(['student_id' => $this->student->id, 'route_name' => 'student.self-program']);
        StudentEvent::factory()->create(['student_id' => $this->student->id, 'kind' => StudentEvent::ACTION, 'detail' => 'library_book_added']);

        Livewire::actingAs(Manager::factory()->create(), 'manager')
            ->test('manager.student-behavior')
            ->assertSee('فتح البرنامج الذاتي 2 مرات ولم يسجّل شيئاً')
            ->assertSee('أضاف كتاباً')
            ->assertSee('جوال');
    });
});
