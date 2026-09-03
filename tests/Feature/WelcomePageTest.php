<?php

use App\Support\KnowledgeHadiths;

it('renders the portal with a single unified login entry point', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('تسجيل الدخول');
    $response->assertSee(route('login'), false);
    $response->assertSee(route('register'), false);
    $response->assertDontSee('/student/login', false);
    $response->assertDontSee('/teacher/login', false);
    $response->assertDontSee('/supervisor/login', false);
});

it('carries only the sections the portal needs', function () {
    // The page is a doorway, not a brochure: the "about", "why us" and live
    // statistics blocks were removed, and the header must not link to anchors
    // that no longer exist.
    $response = $this->get(route('home'))->assertOk();

    $response->assertSee('الأسئلة الشائعة');
    $response->assertSee('تواصل معنا');

    $response->assertDontSee('من نحن');
    $response->assertDontSee('لماذا');
    $response->assertDontSee('إحصاءات حية');
    $response->assertDontSee('data-countup', false);
    $response->assertDontSee('#about', false);
    $response->assertDontSee('#features', false);
});

it('costs nothing to count, since the portal shows no statistics', function () {
    // Dropping the stats block dropped three table counts from every visit to
    // the busiest, least authenticated route in the app.
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get(route('home'))->assertOk();

    expect($queries)->toBe(0);
});

it('names the organisation the way it calls itself', function () {
    config(['brand.entity' => 'الجمعية']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('إدارة الجمعية')
        ->assertDontSee('إدارة المجمع');
});

it('prints the licence when the organisation has one', function () {
    config(['brand.license' => '1000612400']);

    $this->get(route('home'))->assertOk()->assertSee('رقم الترخيص:');
});

it('drops the licence line when no licence is configured', function () {
    config(['brand.license' => '']);

    $this->get(route('home'))->assertOk()->assertDontSee('رقم الترخيص:');
});

it('opens with a hadith on seeking knowledge, named and graded', function () {
    // The academy's subject is the sacred sciences, so the saying on its front
    // door carries its attribution and its grading where a visitor can read
    // them — an unsourced text is the first thing the field would notice.
    $response = $this->get(route('home'))->assertOk();

    $hadith = $response->viewData('hadith');

    expect($hadith)->toHaveKeys(['text', 'source', 'grade']);

    $response->assertSee($hadith['text'], false)
        ->assertSee($hadith['source'], false)
        ->assertSee($hadith['grade'], false);
});

it('carries no verse of the Quran on the portal', function () {
    // The page speaks for an academy of the sacred sciences broadly, not for
    // memorisation alone.
    $this->get(route('home'))->assertOk()->assertDontSee('﴿', false);
});

it('admits only sahih and hasan hadiths', function () {
    $grades = collect(KnowledgeHadiths::all())->pluck('grade');

    expect($grades)->not->toBeEmpty();

    foreach ($grades as $grade) {
        // Diacritics stripped first: "حسّنه" carries a shadda, and "صححه" is
        // not the literal word "صحيح" though it says the same thing.
        $bare = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}]/u', '', $grade);

        expect($bare)->toMatch('/صحيح|صححه|حسن/u');
    }
});

it('gives every hadith its source', function () {
    foreach (KnowledgeHadiths::all() as $hadith) {
        expect(trim($hadith['text']))->not->toBe('')
            ->and(trim($hadith['source']))->not->toBe('')
            ->and(trim($hadith['grade']))->not->toBe('');
    }
});

it('does not always show the same one', function () {
    $seen = collect(range(1, 60))->map(fn () => KnowledgeHadiths::random()['text'])->unique();

    expect($seen->count())->toBeGreaterThan(1);
});

it('describes the organisation the way it configures itself', function () {
    config(['brand.tagline' => 'منصة العلوم الشرعية']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('منصة العلوم الشرعية')
        ->assertDontSee('لتحفيظ القرآن الكريم');
});

it('carries the same hadith treatment onto the sign-in page', function () {
    // The sign-in page is the same front door, and it kept the verse and the
    // line drawings after the portal itself dropped them.
    $response = $this->get(route('login'))->assertOk();

    $response->assertDontSee('﴿', false)
        ->assertSee(config('brand.tagline'))
        ->assertSee('«', false);
});
