<?php

use App\Models\Circle;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\MessagingService;
use App\Services\NotificationService;
use Livewire\Livewire;

it('lets a teacher find, message, and notify a student in their circle end to end', function () {
    $stage = Stage::create(['name' => 'المرحلة الأولى']);
    $circle = Circle::create(['name' => 'دفعة النور', 'stage_id' => $stage->id]);

    $teacher = Teacher::factory()->create(['name' => 'المعلم أحمد']);
    $teacher->circles()->attach($circle->id);

    $student = Student::factory()->create(['name' => 'الطالب محمد', 'circle_id' => $circle->id]);

    $results = MessagingService::searchDirectory('محمد', 'teacher', $teacher->id);
    expect(collect($results)->pluck('name'))->toContain('الطالب محمد');

    $this->actingAs($teacher, 'teacher');

    Livewire::test('messaging.inbox')
        ->call('startComposing')
        ->set('recipientSearch', 'محمد')
        ->call('startConversation', 'student', $student->id)
        ->set('newMessageBody', 'مرحباً يا محمد')
        ->call('sendMessage');

    expect(Message::count())->toBe(1);
    expect(MessagingService::unreadCountFor('student', $student->id))->toBe(1);
    expect(NotificationService::unreadCountFor('student', $student->id))->toBe(1);

    $this->actingAs($student, 'student');
    $conversation = Conversation::first();

    Livewire::test('messaging.inbox')
        ->call('selectConversation', $conversation->id)
        ->assertSee('مرحباً يا محمد');
});

it('shows an error toast instead of silently failing when messaging someone outside any relationship', function () {
    $unrelatedTeacher = Teacher::factory()->create();
    $unrelatedStudent = Student::factory()->create();

    $this->actingAs($unrelatedTeacher, 'teacher');

    Livewire::test('messaging.inbox')
        ->call('startConversation', 'student', $unrelatedStudent->id)
        ->assertDispatched('toast-show');

    expect(Conversation::count())->toBe(0);
});
