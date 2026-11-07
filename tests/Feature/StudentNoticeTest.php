<?php

use App\Models\Circle;
use App\Models\Manager;
use App\Models\Student;
use App\Models\StudentNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Asked for by Faisal, 3 Nov 2026: a message to chosen students by name, and
 * the choice of holding their accounts shut until each types a code he sets.
 */
beforeEach(function () {
    $this->manager = Manager::factory()->create();
    $this->student = Student::factory()->create([
        'name' => 'عمر محمد',
        'circle_id' => Circle::factory()->create()->id,
    ]);
});

describe('the manager', function () {
    it('writes to the students he chose, by name, with a code', function () {
        $other = Student::factory()->create(['name' => 'خالد سعيد', 'circle_id' => $this->student->circle_id]);

        Livewire::actingAs($this->manager, 'manager')
            ->test('manager.student-notices')
            ->set('search', 'عمر')
            ->assertSee('عمر محمد')
            ->call('choose', $this->student->id)
            ->call('choose', $other->id)
            ->set('body', 'السلام عليكم يا {الاسم}، راجع مشرفك.')
            ->set('unlockCode', '1448')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('selected', []);

        expect(StudentNotice::pluck('student_id')->sort()->values()->all())
            ->toBe(collect([$this->student->id, $other->id])->sort()->values()->all())
            ->and(StudentNotice::first()->unlock_code)->toBe('1448')
            ->and(StudentNotice::where('student_id', $this->student->id)->first()->bodyFor('عمر محمد'))
            ->toBe('السلام عليكم يا عمر محمد، راجع مشرفك.');
    });

    it('needs at least one student and a message', function () {
        Livewire::actingAs($this->manager, 'manager')
            ->test('manager.student-notices')
            ->set('body', '')
            ->call('send')
            ->assertHasErrors(['selected', 'body']);
    });

    it('takes a message back before it is opened, which opens the account', function () {
        $notice = StudentNotice::factory()->locked()->create(['student_id' => $this->student->id]);

        Livewire::actingAs($this->manager, 'manager')
            ->test('manager.student-notices')
            ->call('withdraw', $notice->id);

        expect(StudentNotice::count())->toBe(0);
    });
});

describe('the student', function () {
    it('goes about his pages when nothing is waiting', function () {
        $this->actingAs($this->student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk();
    });

    it('is sent to the message before any page, and reads it by name', function () {
        StudentNotice::factory()->create([
            'student_id' => $this->student->id,
            'body' => 'أهلاً يا {الاسم}، موعدك غداً.',
        ]);

        $this->actingAs($this->student, 'student')
            ->get(route('student.dashboard'))
            ->assertRedirect(route('student.notice'));

        $this->actingAs($this->student, 'student')
            ->get(route('student.notice'))
            ->assertOk()
            ->assertSee('أهلاً يا عمر محمد، موعدك غداً.');
    });

    it('goes on once he has read a message with no code', function () {
        StudentNotice::factory()->create(['student_id' => $this->student->id]);

        Livewire::actingAs($this->student, 'student')
            ->test('student.notice')
            ->call('open')
            ->assertRedirect(route('student.dashboard'));

        $this->actingAs($this->student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk();
    });

    it('stays shut until he types the right code, however he types its digits', function () {
        StudentNotice::factory()->locked('1448')->create(['student_id' => $this->student->id]);

        Livewire::actingAs($this->student, 'student')
            ->test('student.notice')
            ->set('code', '1234')
            ->call('open')
            ->assertHasErrors('code')
            ->set('code', ' ١٤٤٨ ')
            ->call('open')
            ->assertHasNoErrors()
            ->assertRedirect(route('student.dashboard'));

        expect(StudentNotice::first()->opened_at)->not->toBeNull();
    });

    it('is slowed down after five wrong codes', function () {
        StudentNotice::factory()->locked('1448')->create(['student_id' => $this->student->id]);
        RateLimiter::clear('student-notice:'.$this->student->id);

        $page = Livewire::actingAs($this->student, 'student')->test('student.notice');

        foreach (range(1, 5) as $try) {
            $page->set('code', '0000')->call('open');
        }

        $page->set('code', '1448')->call('open')->assertHasErrors('code');

        expect(StudentNotice::first()->opened_at)->toBeNull();
    });

    it('is not held by a message written to someone else', function () {
        StudentNotice::factory()->locked()->create();

        $this->actingAs($this->student, 'student')
            ->get(route('student.dashboard'))
            ->assertOk();
    });
});
