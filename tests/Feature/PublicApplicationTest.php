<?php

use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Student;
use App\Models\Supervisor;
use Database\Seeders\NawabighApplicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * A form answered by somebody with no account.
 *
 * Every form before this one was aimed at people already inside the academy,
 * which is right for asking teachers how a term went and useless for the one
 * thing an academy does before anybody is inside it: taking applications.
 */
function publicForm(array $overrides = []): Form
{
    return Form::create(array_merge([
        'title' => 'استمارة تجربة',
        'slug' => 'trial-'.Str::random(6),
        'color' => '#14837B',
        'status' => 'published',
        'published_at' => now(),
        'is_public' => true,
        'public_token' => Str::random(24),
        'fields' => [
            ['id' => 'sec', 'type' => 'section', 'label' => 'بيانات', 'required' => false, 'options' => []],
            ['id' => 'name', 'type' => 'text', 'label' => 'الاسم', 'required' => true, 'options' => [], 'is_student_name' => true],
            ['id' => 'phone', 'type' => 'text', 'label' => 'رقم الجوال', 'required' => true, 'options' => []],
            ['id' => 'grade', 'type' => 'select', 'label' => 'الصف', 'required' => true, 'options' => ['الأول', 'الثاني']],
            ['id' => 'likes', 'type' => 'multiselect', 'label' => 'يميل إلى', 'required' => false, 'options' => ['الرسم', 'البرمجة']],
            ['id' => 'note', 'type' => 'long_text', 'label' => 'ملاحظة', 'required' => false, 'options' => []],
        ],
    ], $overrides));
}

it('opens to a stranger who has the link', function () {
    $form = publicForm();

    $this->get(route('forms.apply', ['token' => $form->public_token]))
        ->assertSuccessful()
        ->assertSee('استمارة تجربة');
});

it('refuses a link that was never opened, or has closed', function () {
    $this->get(route('forms.apply', ['token' => publicForm(['is_public' => false])->public_token]))
        ->assertStatus(410);

    $this->get(route('forms.apply', ['token' => publicForm(['status' => 'draft'])->public_token]))
        ->assertStatus(410);

    // A form closes on its own date, so nobody has to remember to close it the
    // morning after the seats ran out.
    $this->get(route('forms.apply', ['token' => publicForm(['closes_on' => now()->subDay()])->public_token]))
        ->assertStatus(410);

    $this->get(route('forms.apply', ['token' => 'ليس-برمز']))
        ->assertNotFound();
});

it('keeps the answers of somebody who has no account', function () {
    $form = publicForm();

    Livewire::test('public.apply', ['token' => $form->public_token])
        ->set('answers.name', 'سالم بن عبدالله')
        ->set('answers.phone', '0512345678')
        ->set('answers.grade', 'الأول')
        ->set('answers.likes', ['الرسم'])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('done', true);

    $answer = FormResponse::where('form_id', $form->id)->firstOrFail();

    expect($answer->answers['name'])->toBe('سالم بن عبدالله')
        ->and($answer->answers['likes'])->toBe(['الرسم'])
        // Lifted out so the academy can reach him without reading the whole
        // form to find a number — and he has no user row to hold them.
        ->and($answer->respondent_name)->toBe('سالم بن عبدالله')
        ->and($answer->respondent_phone)->toBe('0512345678')
        ->and($answer->student_id)->toBeNull();
});

it('asks for what the form marked required, and no more', function () {
    $form = publicForm();

    Livewire::test('public.apply', ['token' => $form->public_token])
        ->call('submit')
        ->assertHasErrors(['answers.name', 'answers.phone', 'answers.grade'])
        // The optional ones are not demanded, and a divider asks nothing at all.
        ->assertHasNoErrors(['answers.likes', 'answers.note', 'answers.sec'])
        ->assertSet('done', false);

    expect(FormResponse::count())->toBe(0);
});

it('creates no student — an applicant is not one yet', function () {
    $form = publicForm();

    Livewire::test('public.apply', ['token' => $form->public_token])
        ->set('answers.name', 'من تقدّم')
        ->set('answers.phone', '0500000000')
        ->set('answers.grade', 'الثاني')
        ->call('submit');

    // Making him one before anybody has read his answers would fill the academy
    // with people nobody accepted.
    expect(Student::count())->toBe(0);
});

it('refuses an answer sent to a form that closed while it was open on screen', function () {
    $form = publicForm();

    $screen = Livewire::test('public.apply', ['token' => $form->public_token])
        ->set('answers.name', 'متأخّر')
        ->set('answers.phone', '0500000000')
        ->set('answers.grade', 'الأول');

    $form->update(['is_public' => false]);

    // Whatever the page was showing, the answer is refused at the moment it is
    // sent — a form that closed while somebody was filling it in is closed.
    $screen->call('submit')->assertStatus(410);

    expect(FormResponse::count())->toBe(0);
});

/** The programme's own form, as it was seeded. */
it('seeds نوابغ with its five tracks and its own colour', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    $sections = collect($form->fields)->where('type', 'section')->pluck('label');

    expect($form->isOpenToPublic())->toBeTrue()
        ->and($form->color)->toBe('#1B9A8F')
        // The poster's pair, not one colour used twice.
        ->and($form->accent_color)->toBe('#EE6A4D')
        ->and($form->publicUrl())->toContain('/apply/')
        ->and($sections)->toContain('١ · المسار القرآني')
        ->and($sections)->toContain('٢ · المسار العلمي')
        ->and($sections)->toContain('٣ · المسار القيمي')
        ->and($sections)->toContain('٤ · المسار المهاري')
        ->and($sections)->toContain('٥ · المسار الترفيهي')
        // The evening hours are undertaken before the seat is given, not asked
        // about after it is lost — and a partial promise is not on offer.
        ->and(collect($form->fields)->pluck('label')->implode(' '))->toContain('أتعهّد بالتزام ابني')
        ->and(collect($form->fields)->pluck('label')->implode(' '))->not->toContain('التزامه جزئياً');
});

/**
 * The values track asks for scenes, never for scores.
 *
 * A father asked to rate his son's prayer out of five answers five, and a
 * column of fives tells a reading committee nothing it can act on. A father
 * asked what actually happens at prayer time writes a sentence somebody can
 * read a boy out of — so the judging is left to the people who read them, which
 * is where it belongs.
 */
it('leaves the values track open, and puts no scale in it', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $fields = collect(Form::where('slug', 'nawabigh')->firstOrFail()->fields);

    $start = $fields->search(fn (array $f) => ($f['label'] ?? '') === '٣ · المسار القيمي');
    $values = $fields->slice($start + 1)->takeUntil(fn (array $f) => $f['type'] === 'section');

    expect($values)->toHaveCount(2)
        // Two questions only, both prose, and nothing in it judged for them.
        ->and($values->pluck('type')->unique()->all())->toBe(['long_text'])
        ->and($values->pluck('label')->implode(' '))->toContain('تتمنّى أن ترى في ابنك من أخلاق')
        ->and($values->pluck('label')->implode(' '))->toContain('نقاط قوّة ابنك');
});

/**
 * A list a father may have something outside of ends with «غير ذلك», and the
 * form ends with a space to write it in.
 *
 * The school years are the exception and deliberately: they are all the years
 * there are, and offering «غير ذلك» beside them invites an answer nobody can
 * place.
 */
it('opens every list a father might fall outside of, and leaves him the last word', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $fields = collect(Form::where('slug', 'nawabigh')->firstOrFail()->fields);
    $closed = [];

    foreach ($fields->whereIn('type', ['select', 'multiselect']) as $field) {
        if (! in_array('غير ذلك', $field['options'], true)) {
            $closed[] = $field['label'];
        }
    }

    expect($closed)->toBe(['الصف الدراسي في السنة الحالية'], 'قوائم لم تُفتح: '.implode('، ', $closed))
        // And the last word is his, for whatever no list held.
        ->and($fields->last()['type'])->toBe('long_text')
        ->and($fields->last()['label'])->toContain('لم تسعه الخيارات');
});

/**
 * The answers have to arrive somewhere somebody can open.
 *
 * The screen that reads a form's responses lets in its owner, or anybody when
 * the form is shared. A form seeded with neither collects applications into a
 * place no admissions committee can reach — which is worse than not collecting
 * them, because everybody believes it is working.
 */
it('puts an outsider\'s answers where the committee can read them', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    $screen = Livewire::test('public.apply', ['token' => $form->public_token]);

    // Every required question answered, since the form refuses a half-filled
    // application and this is about where a finished one lands.
    foreach ($form->fields as $field) {
        if ($field['type'] === 'section' || ! ($field['required'] ?? false)) {
            continue;
        }

        $screen->set("answers.{$field['id']}", match ($field['type']) {
            'select' => $field['options'][0],
            'multiselect' => [$field['options'][0]],
            'date' => '2016-05-01',
            'yesno' => 'نعم',
            default => str_contains($field['label'], 'الجوال') ? '0512345678'
                : (($field['is_student_name'] ?? false) ? 'سالم المتقدّم' : 'جواب'),
        });
    }

    $screen->call('submit')->assertHasNoErrors();

    $supervisor = Supervisor::factory()->create();

    // He owns nothing and the form is nobody's; the sharing is what lets him in.
    // Read from the screen's own data rather than its markup: the table draws a
    // name through a guessed field map, and a test that reads the page would be
    // testing the guess instead of whether the answer arrived.
    $screen = Livewire::actingAs($supervisor, 'supervisor')
        ->test('supervisor.form-responses', ['formId' => $form->id])
        ->assertSuccessful();

    $arrived = collect($screen->viewData('responses'));

    expect($arrived)->toHaveCount(1)
        ->and($arrived->first()->respondent_name)->toBe('سالم المتقدّم')
        ->and($arrived->first()->respondent_phone)->toBe('0512345678')
        // Still nobody's student, which is what «unprocessed» means here.
        ->and($arrived->first()->student_id)->toBeNull()
        ->and($screen->viewData('unprocessedCount'))->toBe(1);
});

/**
 * Nothing in the form may say the seat is won by the answers.
 *
 * A father who believes the reading committee is scoring him praises his son,
 * inflates what the boy can do and hides what he cannot — and the committee is
 * left with a stack of forms that describe nobody. So the form asks for the
 * truth and says why it helps, and says nothing at all about who is accepted.
 */
it('never hints that acceptance is decided by what the father writes', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    $everything = implode(' ', [
        $form->public_intro,
        $form->success_text,
        $form->description,
        collect($form->fields)->pluck('label')->implode(' '),
    ]);

    foreach (['يُبنى عليه القبول', 'تُظهره الاستمارة', 'على أساس الإجابات', 'يُقبل بحسب'] as $claim) {
        expect($everything)->not->toContain($claim, "الاستمارة توحي بأن القبول مبني على الإجابات: {$claim}");
    }

    // And it does say, plainly, why accuracy is worth the father's trouble.
    expect($form->public_intro)->toContain('الإجابة الدقيقة تساعدنا');
});

/**
 * A birth date asked in Hijri cannot be a date field.
 *
 * The date input is a Gregorian calendar picker whatever its label says, so a
 * father typing ١٤٣٧ would be fighting the widget — or, worse, would pick a
 * Gregorian date and nobody would know which calendar the answer is in.
 */
it('asks the birth date in Hijri, and not through a Gregorian picker', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $birth = collect(Form::where('slug', 'nawabigh')->firstOrFail()->fields)
        ->firstWhere(fn (array $f) => str_contains($f['label'], 'تاريخ الميلاد'));

    expect($birth['label'])->toContain('هجري')
        ->and($birth['type'])->toBe('text');
});
