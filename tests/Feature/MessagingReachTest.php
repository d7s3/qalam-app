<?php

use App\Models\Circle;
use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Stage;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Who may write to whom.
 *
 * The academy's answer is not seniority but shared ground: a guardian reaches
 * the teacher who teaches his son, a student reaches the teacher whose cohort
 * he sits in, and nobody reaches across a cohort they have nothing to do with.
 * The manager reaches everyone, and no one reaches sideways — teacher to
 * teacher, guardian to guardian — because the academy is not a social network.
 *
 * A rule of this shape fails quietly. Widening it by one line opens a channel
 * between a stranger and somebody's child, and nothing on any screen would say
 * so. So the whole matrix is written out rather than sampled: every pair the
 * application can form, with the answer the academy would give.
 *
 * Messaging had no test at all before this.
 */
beforeEach(function () {
    $this->here = Stage::factory()->create(['name' => 'برنامج أ']);
    $this->there = Stage::factory()->create(['name' => 'برنامج ب']);

    $this->hereCohort = Circle::factory()->create(['stage_id' => $this->here->id]);
    $this->thereCohort = Circle::factory()->create(['stage_id' => $this->there->id]);

    $this->teacher = Teacher::factory()->create();
    $this->teacher->circles()->attach($this->hereCohort->id);

    $this->farTeacher = Teacher::factory()->create();
    $this->farTeacher->circles()->attach($this->thereCohort->id);

    $this->supervisor = Supervisor::factory()->create();
    $this->supervisor->stages()->attach($this->here->id);

    $this->farSupervisor = Supervisor::factory()->create();
    $this->farSupervisor->stages()->attach($this->there->id);

    $this->guardian = Guardian::factory()->create();
    $this->farGuardian = Guardian::factory()->create();

    $this->student = Student::factory()->create([
        'circle_id' => $this->hereCohort->id, 'stage_id' => $this->here->id, 'guardian_id' => $this->guardian->id,
    ]);

    $this->farStudent = Student::factory()->create([
        'circle_id' => $this->thereCohort->id, 'stage_id' => $this->there->id, 'guardian_id' => $this->farGuardian->id,
    ]);

    $this->manager = Manager::factory()->create();
});

it('answers every pair the way the academy would', function () {
    $who = [
        'المعلم' => ['teacher', $this->teacher->id],
        'المعلم البعيد' => ['teacher', $this->farTeacher->id],
        'المشرف' => ['supervisor', $this->supervisor->id],
        'المشرف البعيد' => ['supervisor', $this->farSupervisor->id],
        'الطالب' => ['student', $this->student->id],
        'الطالب البعيد' => ['student', $this->farStudent->id],
        'الوليّ' => ['guardian', $this->guardian->id],
        'الوليّ البعيد' => ['guardian', $this->farGuardian->id],
        'المدير' => ['manager', $this->manager->id],
    ];

    // true where the academy says they share ground.
    $expected = [
        'الطالب↔المعلم' => true,
        'الطالب↔المعلم البعيد' => false,
        'الطالب↔المشرف' => true,
        'الطالب↔المشرف البعيد' => false,
        'الطالب↔الطالب البعيد' => false,
        'الطالب↔الوليّ' => true,
        'الطالب↔الوليّ البعيد' => false,
        'الطالب↔المدير' => true,
        'الوليّ↔المعلم' => true,
        'الوليّ↔المعلم البعيد' => false,
        'الوليّ↔المشرف' => true,
        'الوليّ↔المشرف البعيد' => false,
        'الوليّ↔الوليّ البعيد' => false,
        'الوليّ↔الطالب البعيد' => false,
        'الوليّ↔المدير' => true,
        'المعلم↔المشرف' => true,
        'المعلم↔المشرف البعيد' => false,
        'المعلم↔المعلم البعيد' => false,
        'المعلم↔المدير' => true,
        'المشرف↔المشرف البعيد' => false,
        'المشرف↔المدير' => true,
    ];

    $wrong = [];

    foreach ($expected as $pair => $allowed) {
        [$a, $b] = explode('↔', $pair);
        [$typeA, $idA] = $who[$a];
        [$typeB, $idB] = $who[$b];

        $answer = MessagingService::isAllowedToMessage($typeA, $idA, $typeB, $idB);
        $back = MessagingService::isAllowedToMessage($typeB, $idB, $typeA, $idA);

        if ($answer !== $allowed) {
            $wrong[] = "{$pair} ← ".($answer ? 'مسموح' : 'ممنوع').' والصواب '.($allowed ? 'مسموح' : 'ممنوع');
        }

        // A channel is a channel from either end, or one of them is writing
        // into a room the other cannot answer in.
        if ($answer !== $back) {
            $wrong[] = "{$pair} ← يختلف الجواب باختلاف من يسأل";
        }
    }

    expect($wrong)->toBe([], PHP_EOL.'  '.implode(PHP_EOL.'  ', $wrong).PHP_EOL);
});

it('never lets anybody write to himself', function () {
    foreach ([['teacher', $this->teacher->id], ['student', $this->student->id], ['manager', $this->manager->id]] as [$type, $id]) {
        expect(MessagingService::isAllowedToMessage($type, $id, $type, $id))->toBeFalse($type);
    }
});

it('keeps the directory to the people its searcher may write to', function () {
    $found = collect(MessagingService::searchDirectory('', 'student', $this->student->id))
        ->pluck('id')->all();

    // Whoever the search offers must be somebody the rule allows, or the screen
    // invites a message the service will refuse.
    foreach (MessagingService::searchDirectory('', 'student', $this->student->id) as $row) {
        expect(MessagingService::isAllowedToMessage('student', $this->student->id, $row['type'], $row['id']))
            ->toBeTrue("العرض اقترح {$row['type']}#{$row['id']} ولا يُسمح بمراسلته");
    }

    expect($found)->not->toBeEmpty('لا أحد في الدليل — الاختبار يمرّ على الفراغ');
});
