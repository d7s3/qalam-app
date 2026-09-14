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

    // A scale is closed by its nature — «غير ذلك» beside «ممتاز» and «ضعيف» is
    // not another grade, it is a way out of answering — and the school years
    // are all the years there are.
    $expected = [
        'الصف الدراسي في السنة الحالية',
        'إتقانه لما حفظ',
        'مستواه الدراسي العام في آخر فصل',
        'قدرته على الحفظ',
        'قدرته على الفهم',
        'رقم الجوال (للاتصال)',
    ];

    sort($closed);
    sort($expected);

    expect($closed)->toBe($expected, 'قوائم لم تُفتح: '.implode('، ', $closed))
        // And the form ends with two open pages: one for whatever no list held
        // about the boy, and one that is not about the boy at all.
        ->and($fields->slice(-2)->pluck('type')->all())->toBe(['long_text', 'long_text'])
        ->and($fields->slice(-2)->first()['label'])->toContain('شيء إضافي عن الابن')
        ->and($fields->last()['label'])->toContain('مساحة حرّة');
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

        // A date is chosen from three lists now, not typed, so setting the
        // answer directly would be overwritten the moment they are assembled.
        if (($field['picker'] ?? null) === 'hijri') {
            $screen->set("hijri.{$field['id']}.day", '12')
                ->set("hijri.{$field['id']}.month", '5')
                ->set("hijri.{$field['id']}.year", '1437');

            continue;
        }

        $screen->set("answers.{$field['id']}", match ($field['type']) {
            'select' => $field['options'][0],
            'multiselect' => [$field['options'][0]],
            'date' => '2016-05-01',
            'yesno' => 'نعم',
            default => str_contains($field['label'], 'الجوال') || str_contains($field['label'], 'واتساب')
                ? '0512345678'
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
        // A plain boolean: toContain takes its needles variadically, so «not»
        // with a message beside a needle asks whether BOTH are present — and
        // the message never is, so the guard passes whatever the form says.
        expect(str_contains($everything, $claim))
            ->toBeFalse("الاستمارة توحي بأن القبول مبني على الإجابات: {$claim}");
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

/**
 * Seeding twice must not orphan the applications already received.
 *
 * An answer is stored under the id of the field it answered. When those ids
 * were drawn at random, a second run of the seeder — to fix a word, to add a
 * question — renamed every field, and every application already in the database
 * was left keyed to questions that no longer existed: present, complete, and
 * readable by nobody.
 */
it('keeps its field ids when it is seeded again', function () {
    $this->seed(NawabighApplicationSeeder::class);
    $first = collect(Form::where('slug', 'nawabigh')->firstOrFail()->fields)->pluck('id');

    // An application arrives between the two runs, as one would.
    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    FormResponse::create([
        'form_id' => $form->id,
        'answers' => [$first->get(1) => 'سالم بن عبدالله'],
        'respondent_name' => 'سالم بن عبدالله',
    ]);

    $this->seed(NawabighApplicationSeeder::class);
    $second = collect(Form::where('slug', 'nawabigh')->firstOrFail()->fields)->pluck('id');

    expect($second->all())->toBe($first->all())
        ->and($first->unique())->toHaveCount($first->count());

    // And the answer still names the question it answered.
    $answer = FormResponse::where('form_id', $form->id)->firstOrFail();
    $asked = collect($form->fresh()->fields)->firstWhere('id', array_key_first($answer->answers));

    expect($asked)->not->toBeNull()
        ->and($answer->answers[$asked['id']])->toBe('سالم بن عبدالله');
});

/**
 * «غير ذلك» has to record the thing itself.
 *
 * Every list in this form was opened with «غير ذلك» so a father would never be
 * cornered into an answer that is not his — and then the option recorded the
 * two words and nothing else. A committee reading «غير ذلك» in forty forms has
 * learnt only that forty fathers had something to say.
 */
it('keeps what a father writes beside an open choice, in place of the choice', function () {
    $form = publicForm([
        'fields' => [
            ['id' => 'name', 'type' => 'text', 'label' => 'الاسم', 'required' => true, 'options' => [], 'is_student_name' => true],
            ['id' => 'tie', 'type' => 'select', 'label' => 'صلته', 'required' => true, 'options' => ['الأب', 'غير ذلك']],
            ['id' => 'likes', 'type' => 'multiselect', 'label' => 'يميل إلى', 'required' => false, 'options' => ['الرسم', 'غير ذلك']],
        ],
    ]);

    Livewire::test('public.apply', ['token' => $form->public_token])
        ->set('answers.name', 'سالم')
        ->set('answers.tie', 'غير ذلك')
        ->set('written.tie', 'عمّه')
        ->set('answers.likes', ['الرسم', 'غير ذلك'])
        ->set('written.likes', 'تربية النحل')
        ->call('submit')
        ->assertHasNoErrors();

    $answers = FormResponse::where('form_id', $form->id)->firstOrFail()->answers;

    expect($answers['tie'])->toBe('عمّه')
        // The ticked choices he did make are untouched; only the open one is
        // replaced by what he wrote in it.
        ->and($answers['likes'])->toBe(['الرسم', 'تربية النحل']);
});

it('refuses a box that opened and was left empty', function () {
    $form = publicForm([
        'fields' => [
            ['id' => 'name', 'type' => 'text', 'label' => 'الاسم', 'required' => true, 'options' => [], 'is_student_name' => true],
            ['id' => 'tie', 'type' => 'select', 'label' => 'صلته', 'required' => true, 'options' => ['الأب', 'غير ذلك']],
        ],
    ]);

    Livewire::test('public.apply', ['token' => $form->public_token])
        ->set('answers.name', 'سالم')
        ->set('answers.tie', 'غير ذلك')
        ->call('submit')
        // Choosing «غير ذلك» and writing nothing is the question unanswered,
        // whatever the list above it says.
        ->assertHasErrors(['written.tie']);

    expect(FormResponse::count())->toBe(0);
});

/**
 * A question may name its own opening option.
 *
 * «رقم آخر» asks for a number, not for the words «غير ذلك» — so the box is
 * opened by the choice the question names rather than by one fixed phrase.
 */
it('opens the box on whichever choice the question names', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    $calling = collect($form->fields)->firstWhere('label', 'رقم الجوال (للاتصال)');

    expect($calling['write_in'])->toBe(['رقم آخر'])
        ->and($calling['options'])->toBe(['نفس رقم الواتساب', 'رقم آخر']);

    $screen = Livewire::test('public.apply', ['token' => $form->public_token]);

    expect($screen->instance()->wantsWriting($calling))->toBeFalse();

    $screen->set("answers.{$calling['id']}", 'رقم آخر');

    expect($screen->instance()->wantsWriting($calling))->toBeTrue();
});

/**
 * The WhatsApp number is the only way back to the family.
 *
 * A number written in Arabic-Indic digits, or with spaces and dashes through
 * it, is a number nobody can paste into WhatsApp — so the shape is shown above
 * the box and then held to.
 */
it('holds the WhatsApp number to a shape somebody can actually dial', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    $number = collect($form->fields)->firstWhere('label', 'رقم الواتساب');

    expect($number['hint'])->toContain('05xxxxxxxx');

    $answer = fn (string $typed) => Livewire::test('public.apply', ['token' => $form->public_token])
        ->set("answers.{$number['id']}", $typed)
        ->call('submit');

    foreach (['٠٥٠١٢٣٤٥٦٧', '050 123 4567', '0501-234567', '12345'] as $wrong) {
        $answer($wrong)->assertHasErrors(["answers.{$number['id']}"]);
    }

    foreach (['0501234567', '966501234567', '+966501234567'] as $right) {
        $answer($right)->assertHasNoErrors(["answers.{$number['id']}"]);
    }
});

/** The two capacities this programme leans on are asked outright, and on one scale. */
it('asks about memorising and understanding on a scale of words', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $fields = collect(Form::where('slug', 'nawabigh')->firstOrFail()->fields);
    $scale = ['متميّز جداً', 'ممتاز', 'متوسط', 'أقلّ من المتوسط', 'ضعيف'];

    foreach (['قدرته على الحفظ', 'قدرته على الفهم'] as $label) {
        $question = $fields->firstWhere('label', $label);

        expect($question)->not->toBeNull("سؤال مفقود: {$label}")
            ->and($question['type'])->toBe('select')
            ->and($question['required'])->toBeTrue()
            // No «غير ذلك» on a scale: it is not another grade, it is a way out
            // of answering.
            ->and($question['options'])->toBe($scale);
    }

    // And mastery of what he has memorised is words too, never a number out of
    // five — a father marking his son six and one marking him seven mean
    // nothing beside each other.
    expect($fields->firstWhere('label', 'إتقانه لما حفظ')['type'])->toBe('select');
});

/**
 * The number the academy keeps has to be a number.
 *
 * The form asks for two: the WhatsApp number, and a calling number that is
 * usually «نفس رقم الواتساب» — words, not a number. Lifting the first field
 * whose label says «جوال» stored that sentence as the only way back to the
 * family, and nobody would have found out until somebody tried to ring.
 */
it('lifts a number the academy can actually ring', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    $fill = function (string $calling, string $written = '') use ($form) {
        $screen = Livewire::test('public.apply', ['token' => $form->public_token]);

        foreach ($form->fields as $field) {
            if ($field['type'] === 'section' || ! ($field['required'] ?? false)) {
                continue;
            }

            if (($field['picker'] ?? null) === 'hijri') {
                $screen->set("hijri.{$field['id']}.day", '12')
                    ->set("hijri.{$field['id']}.month", '5')
                    ->set("hijri.{$field['id']}.year", '1437');

                continue;
            }

            $screen->set("answers.{$field['id']}", match (true) {
                $field['label'] === 'رقم الجوال (للاتصال)' => $calling,
                str_contains($field['label'], 'واتساب') => '0501234567',
                $field['type'] === 'select' => $field['options'][0],
                $field['type'] === 'multiselect' => [$field['options'][0]],
                $field['type'] === 'yesno' => 'نعم',
                default => ($field['is_student_name'] ?? false) ? 'سالم' : 'جواب',
            });

            if ($field['label'] === 'رقم الجوال (للاتصال)' && $written !== '') {
                $screen->set("written.{$field['id']}", $written);
            }
        }

        $screen->call('submit')->assertHasNoErrors();

        return FormResponse::where('form_id', $form->id)->latest('id')->firstOrFail();
    };

    // He has one number, and says so in words.
    expect($fill('نفس رقم الواتساب')->respondent_phone)->toBe('0501234567');

    // He has another, and writes it — and that is the one kept, not «رقم آخر».
    $second = $fill('رقم آخر', '0559876543');

    expect($second->respondent_phone)->toBe('0501234567')
        ->and(collect($second->answers)->contains('0559876543'))->toBeTrue()
        ->and(collect($second->answers)->contains('رقم آخر'))->toBeFalse();
});

/**
 * And it has to be a number whichever question comes first.
 *
 * In نوابغ the WhatsApp number happens to be asked before the calling one, so
 * taking the first field whose label mentions a phone lands on a real number by
 * luck of ordering. Move the questions — or write another form — and the luck
 * runs out, so the value is checked and not just the label.
 */
it('passes over a phone question whose answer is not a phone', function () {
    $form = publicForm([
        'fields' => [
            ['id' => 'name', 'type' => 'text', 'label' => 'الاسم', 'required' => true, 'options' => [], 'is_student_name' => true],
            ['id' => 'calling', 'type' => 'select', 'label' => 'رقم الجوال (للاتصال)', 'required' => true, 'options' => ['نفس رقم الواتساب', 'رقم آخر']],
            ['id' => 'whats', 'type' => 'text', 'label' => 'رقم الواتساب', 'required' => true, 'options' => []],
        ],
    ]);

    Livewire::test('public.apply', ['token' => $form->public_token])
        ->set('answers.name', 'سالم')
        ->set('answers.calling', 'نفس رقم الواتساب')
        ->set('answers.whats', '0501234567')
        ->call('submit')
        ->assertHasNoErrors();

    expect(FormResponse::where('form_id', $form->id)->firstOrFail()->respondent_phone)
        ->toBe('0501234567');
});

/**
 * What a father must read before he sends, not after.
 *
 * Two things belong at the end of an application and nowhere else: what the
 * term costs, and that sending the form is not being accepted into it. Putting
 * them in `policy_text` would set them in the small grey type the privacy line
 * uses, which is where things go to be unread — and a family that discovers the
 * fee after believing its son was accepted has been badly treated.
 */
it('closes with the fee and with what the form is not', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();

    expect($form->closing_note)->toContain('ليس إعلاناً بالقبول')
        ->and($form->closing_note)->toContain('٩٥٠')
        ->and($form->closing_note)->toContain('خمسة أيام أسبوعياً')
        ->and($form->closing_note)->toContain('ثلاث ساعات ونصف يومياً')
        ->and($form->closing_note)->toContain('الأنشطة الداخلية الأسبوعية')
        ->and($form->closing_note)->toContain('٣ أشهر تعليمية')
        // Its own field, not folded into the privacy line.
        ->and($form->policy_text)->not->toContain('٩٥٠');

    // And it reaches the page a stranger opens.
    $page = $this->get(route('forms.apply', ['token' => $form->public_token]))
        ->assertSuccessful()
        ->getContent();

    foreach (['ليس إعلاناً بالقبول', '٩٥٠', 'الأنشطة الداخلية الأسبوعية'] as $said) {
        expect(str_contains($page, $said))->toBeTrue("الخاتمة لا تظهر للمتقدّم: {$said}");
    }
});

/**
 * The pledge and the fee describe the same week.
 *
 * The fee says five days; the pledge said Sunday to Wednesday, which is four.
 * A father reading both learns that the academy does not know its own timetable
 * — and one of the two numbers is what he will hold it to.
 */
it('promises the same week the fee is charged for', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    $pledge = collect($form->fields)->first(fn (array $f) => str_contains($f['label'], 'أتعهّد'));

    expect($pledge['label'])->toContain('من الأحد إلى الخميس')
        ->and($form->closing_note)->toContain('خمسة أيام أسبوعياً')
        // And the same sitting: five to half past eight is three and a half
        // hours, which is what the fee line has to say it is.
        ->and($pledge['label'])->toContain('من الخامسة إلى الثامنة والنصف')
        ->and($form->closing_note)->toContain('ثلاث ساعات ونصف يومياً');
});

/**
 * The birth date is chosen, and chosen in the calendar it was asked for.
 *
 * It was a plain text box because the browser's date control is a Gregorian
 * calendar however the label above it reads — a father typing ١٤٣٧ would have
 * been fighting it. Three lists are a picker too, and they pick Hijri.
 */
it('picks the birth date from three Hijri lists', function () {
    $this->seed(NawabighApplicationSeeder::class);

    $form = Form::where('slug', 'nawabigh')->firstOrFail();
    $birth = collect($form->fields)->first(fn (array $f) => str_contains($f['label'], 'تاريخ الميلاد'));

    expect($birth['picker'])->toBe('hijri');

    $screen = Livewire::test('public.apply', ['token' => $form->public_token]);

    foreach ($form->fields as $field) {
        if ($field['type'] === 'section' || ! ($field['required'] ?? false) || $field['id'] === $birth['id']) {
            continue;
        }

        $screen->set("answers.{$field['id']}", match (true) {
            str_contains($field['label'], 'واتساب') => '0501234567',
            $field['type'] === 'select' => $field['options'][0],
            $field['type'] === 'multiselect' => [$field['options'][0]],
            $field['type'] === 'yesno' => 'نعم',
            default => ($field['is_student_name'] ?? false) ? 'سالم' : 'جواب',
        });
    }

    // A date left half-chosen is no date, and the question is required.
    $screen->set("hijri.{$birth['id']}.day", '12')
        ->set("hijri.{$birth['id']}.month", '5')
        ->call('submit')
        ->assertHasErrors(["answers.{$birth['id']}"]);

    $screen->set("hijri.{$birth['id']}.year", '1437')
        ->call('submit')
        ->assertHasNoErrors();

    expect(FormResponse::where('form_id', $form->id)->latest('id')->firstOrFail()->answers[$birth['id']])
        ->toBe('1437/05/12 هـ');
});
