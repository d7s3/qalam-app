<?php

use App\Models\Form;
use App\Models\FormResponse;
use App\Models\Student;
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

    expect($values)->not->toBeEmpty()
        // Every question in it is prose, and nothing in it is judged for them.
        ->and($values->pluck('type')->unique()->all())->toBe(['long_text'])
        ->and($values->pluck('type')->intersect(['likert', 'rating', 'nps', 'select']))->toBeEmpty();
});
