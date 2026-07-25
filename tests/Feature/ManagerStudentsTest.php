<?php

use App\Livewire\Manager\Students;
use App\Models\Circle;
use App\Models\Manager;
use App\Models\Student;
use Livewire\Livewire;

it('allows manager to edit and save a student using Students livewire component', function () {
    $manager = Manager::factory()->create();
    $circle = Circle::factory()->create();
    $student = Student::factory()->create([
        'circle_id' => $circle->id,
        'status' => 'active',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(Students::class)
        ->call('edit', $student->id)
        ->assertSet('editingStudentId', $student->id)
        ->set('name', 'Updated Student Name')
        ->call('save')
        ->assertHasNoErrors();

    expect($student->refresh()->name)->toBe('Updated Student Name');
});

it('changes the student status through the dedicated status manager', function () {
    $manager = Manager::factory()->create();
    $circle = Circle::factory()->create();
    $student = Student::factory()->create([
        'circle_id' => $circle->id,
        'status' => 'active',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test('shared.⚡student-status-manager')
        ->call('open', $student->id)
        ->set('newStatus', 'suspended')
        ->set('reason', 'انقطاع متكرر عن الحضور')
        ->call('saveStatus')
        ->assertHasNoErrors();

    expect($student->refresh()->status)->toBe('suspended');
});

it('builds a copyable text of selected student names and magic links', function () {
    $manager = Manager::factory()->create();
    $circle = Circle::factory()->create();
    $selected = Student::factory()->create([
        'circle_id' => $circle->id,
        'name' => 'أحمد',
        'access_token' => 'manager-token-one',
    ]);
    $unselected = Student::factory()->create([
        'circle_id' => $circle->id,
        'name' => 'غير محدد',
        'access_token' => 'manager-token-two',
    ]);

    $this->actingAs($manager, 'manager');

    $component = Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $selected->id])
        ->assertSee('نسخ الأسماء والروابط')
        ->call('buildSelectedMagicLinksText')
        ->assertHasNoErrors();

    expect($component->effects['returns'][0])
        ->toContain('أحمد')
        ->toContain(route('magic-link', ['token' => 'manager-token-one']))
        ->not->toContain('غير محدد')
        ->not->toContain('manager-token-two');
});

it('keeps selected students while the manager searches for more', function () {
    $manager = Manager::factory()->create();
    $circle = Circle::factory()->create();
    $first = Student::factory()->create(['circle_id' => $circle->id, 'name' => 'أحمد']);
    $second = Student::factory()->create(['circle_id' => $circle->id, 'name' => 'بدر']);

    $this->actingAs($manager, 'manager');

    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $first->id])
        // Searching for someone else must not drop the first pick.
        ->set('search', 'بدر')
        ->assertSet('selectedStudentIds', [(string) $first->id])
        ->set('selectedStudentIds', [(string) $first->id, (string) $second->id])
        // Nor must changing a filter afterwards.
        ->set('circleFilter', $circle->id)
        ->assertSet('selectedStudentIds', [(string) $first->id, (string) $second->id])
        ->assertSee('نسخ الأسماء والروابط');
});

it('clears the header checkbox but not the selection when filters change', function () {
    $manager = Manager::factory()->create();
    $student = Student::factory()->create(['circle_id' => Circle::factory()->create()->id]);

    $this->actingAs($manager, 'manager');

    Livewire::test(Students::class)
        ->set('selectAll', true)
        ->assertSet('selectAll', true)
        ->set('search', 'لا أحد')
        ->assertSet('selectAll', false)
        ->assertSet('selectedStudentIds', [(string) $student->id]);
});

it('reports how many selected students the active filters hide', function () {
    $manager = Manager::factory()->create();
    $circle = Circle::factory()->create();
    $visible = Student::factory()->create(['circle_id' => $circle->id, 'name' => 'ظاهر']);
    $hidden = Student::factory()->create([
        'circle_id' => Circle::factory()->create()->id,
        'name' => 'مخفي',
    ]);

    $this->actingAs($manager, 'manager');

    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $visible->id, (string) $hidden->id])
        ->set('circleFilter', $circle->id)
        ->assertSee('خارج نتائج البحث الحالية')
        ->call('selectedOutsideFiltersCount')
        ->assertReturned(1);
});

it('lets the manager clear the whole selection at once', function () {
    $manager = Manager::factory()->create();
    $student = Student::factory()->create(['circle_id' => Circle::factory()->create()->id]);

    $this->actingAs($manager, 'manager');

    Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $student->id])
        ->call('resetStudentSelection')
        ->assertSet('selectedStudentIds', [])
        ->assertSet('selectAll', false);
});

it('selects every filtered student when the manager ticks select all', function () {
    $manager = Manager::factory()->create();
    $circle = Circle::factory()->create();
    $otherCircle = Circle::factory()->create();
    $inCircle = Student::factory()->create(['circle_id' => $circle->id]);
    $outOfCircle = Student::factory()->create(['circle_id' => $otherCircle->id]);

    $this->actingAs($manager, 'manager');

    Livewire::test(Students::class)
        ->set('circleFilter', $circle->id)
        ->set('selectAll', true)
        ->assertSet('selectedStudentIds', [(string) $inCircle->id]);

    expect($outOfCircle->exists)->toBeTrue();
});

it('issues a magic link token to selected students that never had one', function () {
    $manager = Manager::factory()->create();
    $student = Student::factory()->create([
        'circle_id' => Circle::factory()->create()->id,
        'access_token' => null,
    ]);

    $this->actingAs($manager, 'manager');

    $component = Livewire::test(Students::class)
        ->set('selectedStudentIds', [(string) $student->id])
        ->call('buildSelectedMagicLinksText');

    $token = $student->refresh()->access_token;

    expect($token)->not->toBeEmpty();
    expect($component->effects['returns'][0])->toContain(route('magic-link', ['token' => $token]));
});
