<?php

use App\Models\Manager;
use App\Models\Motivation;
use App\Models\PortalMessageRead;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\PortalService;

/**
 * Whether the notice holding the button with this word lies wholly within
 * the screen, its first line as well as its last, with nothing — a bottom
 * bar, say — laid over the button.
 */
function openingNoticeFitsScreen(string $label): string
{
    return <<<JS
        (() => {
            const button = [...document.querySelectorAll('.fixed.inset-0 button')].find(b => b.textContent.trim() === '{$label}');
            const box = button.closest('.rounded-2xl').getBoundingClientRect();
            const spot = button.getBoundingClientRect();
            const atButton = document.elementFromPoint(spot.left + spot.width / 2, spot.top + spot.height / 2);

            return box.top >= 0 && box.bottom <= window.innerHeight && button.contains(atButton);
        })()
        JS;
}

/** A shahid as long as one may be and still be shown on opening. */
function longestShahid(): string
{
    return mb_substr(str_repeat('من أراد العلم فليصبر على مرارة الطلب، فإن العلم لا يُنال براحة الجسد. ', 5), 0, Motivation::OPENING_LENGTH);
}

/**
 * What meets a person on opening — a shahid, or a word addressed to him — sits
 * over the whole page. A long one used to run past the bottom of a phone,
 * taking the button that closes it out of reach, and nothing behind it could
 * be scrolled. However long it is, it has to fit the screen it is read on.
 */
beforeEach(function () {
    $this->teacher = Teacher::factory()->create(['is_approved' => true]);

    $this->actingAs($this->teacher, 'teacher');
});

it('fits the longest shahid to a phone\'s screen, its first line and its close button', function () {
    Motivation::create([
        'kind' => 'athar',
        'text' => longestShahid(),
        'source' => 'أثر طويل',
        'status' => 'approved',
    ]);

    visit('/teacher/students')
        ->on()->mobile()
        ->assertNoJavaScriptErrors()
        ->assertSee('أثر طويل')
        ->assertScript(openingNoticeFitsScreen('إغلاق'), true);
});

it('fits a long message to a phone\'s screen, its title and its read button', function () {
    PortalService::announce(
        Manager::factory()->create(),
        'manager',
        str_repeat("نرجو من الجميع الالتزام بمواعيد الحلقات والحضور قبل بدء الدرس بعشر دقائق.\n", 25),
        ['teacher'],
        title: 'رسالة طويلة',
    );

    visit('/teacher/students')
        ->on()->mobile()
        ->assertNoJavaScriptErrors()
        ->assertSee('رسالة طويلة')
        ->assertScript(openingNoticeFitsScreen('قرأتها'), true);
});

it('fits the longest shahid to a student\'s phone too, over his own bottom bar', function () {
    $this->actingAs(Student::factory()->create(['is_data_completed' => true]), 'student');

    Motivation::create([
        'kind' => 'athar',
        'text' => longestShahid(),
        'source' => 'أثر طويل',
        'status' => 'approved',
    ]);

    visit('/student/dashboard')
        ->on()->mobile()
        ->assertNoJavaScriptErrors()
        ->assertScript(openingNoticeFitsScreen('إغلاق'), true);
});

it('closes a shahid with its button, and greets no more on the next page', function () {
    Motivation::create(['kind' => 'athar', 'text' => 'العلم صيد والكتابة قيده', 'source' => 'أثر قصير', 'status' => 'approved']);

    visit('/teacher/students')
        ->on()->mobile()
        ->assertSee('أثر قصير')
        ->click('.z-overlay button')
        ->assertDontSee('أثر قصير')
        ->navigate('/teacher/students')
        ->assertDontSee('أثر قصير');
});

it('closes a shahid with Escape', function () {
    Motivation::create(['kind' => 'athar', 'text' => 'العلم صيد والكتابة قيده', 'source' => 'أثر قصير', 'status' => 'approved']);

    visit('/teacher/students')
        ->assertSee('أثر قصير')
        ->keys('.z-overlay', 'Escape')
        ->assertDontSee('أثر قصير');
});

it('takes a word away once he says he read it, and records the reading', function () {
    PortalService::announce(Manager::factory()->create(), 'manager', 'ذكّروا طلابكم بالمراجعة', ['teacher'], title: 'تذكير');

    visit('/teacher/students')
        ->on()->mobile()
        ->assertSee('تذكير')
        ->keys('.z-overlay', 'Escape')
        ->assertSee('تذكير')
        ->click('.z-overlay button')
        ->assertDontSee('تذكير');

    expect(PortalMessageRead::where('user_id', $this->teacher->id)->count())->toBe(1);
});

it('greets once on opening, not again on every page he opens after it', function () {
    Motivation::create(['kind' => 'athar', 'text' => 'العلم صيد والكتابة قيده', 'source' => 'أثر قصير', 'status' => 'approved']);

    visit('/teacher/students')
        ->assertSee('أثر قصير')
        ->navigate('/teacher/attendance')
        ->assertDontSee('أثر قصير')
        ->navigate('/teacher/dashboard')
        ->assertDontSee('أثر قصير');
});
