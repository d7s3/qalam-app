<?php

use App\Models\Circle;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentPlacementRequest;
use App\Models\Supervisor;
use App\Models\UserRole;
use App\Services\NotificationService;
use App\Services\StudentPlacementService;
use App\Support\Scope;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A student without a group chooses his sheikh, and waits for him.
 *
 * Nobody could see a student who signed himself up, so he waited for the
 * manager to remember him. He now names his sheikh's group himself; only that
 * sheikh hears of it, and he can change his mind until he is accepted.
 */
beforeEach(function () {
    $programme = Stage::factory()->create(['name' => 'تأصيل']);
    $this->ahdal = Circle::factory()->create(['stage_id' => $programme->id, 'name' => 'الأهدل']);
    $this->filimban = Circle::factory()->create(['stage_id' => $programme->id, 'name' => 'فلمبان']);

    $this->sheikhAhdal = sheikhOver($programme, $this->ahdal, 'عبدالرحمن الأهدل');
    $this->sheikhFilimban = sheikhOver($programme, $this->filimban, 'جابر فلمبان');

    $this->student = Student::factory()->create(['circle_id' => null, 'stage_id' => null, 'status' => 'registering', 'is_approved' => true, 'is_data_completed' => true]);

    Scope::forget();
    $this->actingAs($this->student, 'student');
});

function sheikhOver(Stage $programme, Circle $group, string $name): Supervisor
{
    $sheikh = Supervisor::factory()->create(['name' => $name]);
    $sheikh->stages()->attach($programme->id);
    $sheikh->roles()->where('role', 'supervisor')->update(['scope_type' => UserRole::SCOPE_CIRCLES, 'scope_ids' => [$group->id]]);

    return $sheikh->load('roles');
}

it('offers each group by its sheikh\'s name', function () {
    Livewire::test('student.choose-group')
        ->assertSee('اختر مجموعتك')
        ->assertSee('الشيخ عبدالرحمن الأهدل')
        ->assertSee('الشيخ جابر فلمبان');
});

it('asks his sheikh for him, and tells that sheikh alone', function () {
    Livewire::test('student.choose-group')
        ->set('picked', $this->ahdal->id)
        ->call('send')
        ->assertHasNoErrors()
        ->assertSee('بانتظار قبول')
        ->assertSee('الشيخ عبدالرحمن الأهدل');

    $ask = StudentPlacementRequest::pending()->sole();

    expect([$ask->student_id, $ask->circle_id, $ask->requested_by])->toBe([$this->student->id, $this->ahdal->id, $this->student->id])
        ->and(NotificationService::unreadCountFor('supervisor', $this->sheikhAhdal->id))->toBe(1)
        ->and(NotificationService::unreadCountFor('supervisor', $this->sheikhFilimban->id))->toBe(0);
});

it('says he must choose a group before sending', function () {
    Livewire::test('student.choose-group')
        ->call('send')
        ->assertHasErrors(['picked' => 'required']);

    expect(StudentPlacementRequest::count())->toBe(0);
});

it('withdraws his first ask when he chooses again', function () {
    StudentPlacementService::askToJoin($this->student, $this->ahdal);

    Livewire::test('student.choose-group')
        ->call('change')
        ->set('picked', $this->filimban->id)
        ->call('send');

    expect(StudentPlacementRequest::pending()->pluck('circle_id')->all())->toBe([$this->filimban->id])
        ->and(StudentPlacementRequest::where('circle_id', $this->ahdal->id)->value('status'))->toBe(StudentPlacementRequest::REJECTED);
});

it('shows nothing to a student already in a group, and lets him ask for none', function () {
    $this->student->update(['circle_id' => $this->ahdal->id]);

    Livewire::test('student.choose-group')->assertDontSee('اختر مجموعتك');

    expect(fn () => StudentPlacementService::askToJoin($this->student->fresh(), $this->filimban))
        ->toThrow(HttpException::class);
});

it('tells him when his sheikh did not take him, so he chooses again', function () {
    $ask = StudentPlacementService::askToJoin($this->student, $this->ahdal);
    StudentPlacementService::reject($ask, $this->sheikhAhdal, 'المجموعة مكتملة');

    expect(NotificationService::unreadCountFor('student', $this->student->id))->toBe(1);

    Livewire::test('student.choose-group')
        ->assertSee('لم يُقبل طلبك في')
        ->assertSee('المجموعة مكتملة')
        ->assertSee('اختر مجموعتك');
});

it('greets him with the choice on his home page', function () {
    $this->get(route('student.dashboard'))->assertSuccessful()->assertSee('اختر مجموعتك');
});

it('puts the sheikh\'s family letter on his group\'s tile, without «ال»', function () {
    $component = Livewire::test('student.choose-group')->instance();

    expect($component->initialOf(['sheikhs' => ['عبدالرحمن الأهدل'], 'circle' => $this->ahdal]))->toBe('أ')
        ->and($component->initialOf(['sheikhs' => ['جابر فلمبان'], 'circle' => $this->filimban]))->toBe('ف');
});
