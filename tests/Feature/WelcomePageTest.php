<?php

use App\Support\LearningSayings;

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

it('opens with a saying on learning, and names who said it', function () {
    // An unattributed line on a front door is worth nothing to a reader, and
    // the attribution is also what keeps the page honest about whose words
    // these are.
    $response = $this->get(route('home'))->assertOk();

    $saying = $response->viewData('saying');

    expect($saying)->toHaveKeys(['text', 'source']);

    $response->assertSee($saying['text'], false)
        ->assertSee($saying['source'], false);
});

/**
 * The doorway speaks for every school that is handed this platform.
 *
 * It used to open with a hadith, which speaks for one kind of academy and not
 * for the rest. A religious text here — a verse, a hadith, an invocation —
 * would narrow the platform to the organisation it was first written for.
 */
it('carries no religious text on the portal', function () {
    $page = $this->get(route('home'))->assertOk()->getContent();

    foreach (['﴿', '﴾', 'ﷺ', 'صلى الله عليه', 'رضي الله عن', 'رواه ', 'حديث'] as $religious) {
        // Asked as a plain boolean: toContain takes its needles variadically,
        // so a message passed beside one is read as a second needle and the
        // guard quietly stops guarding.
        expect(str_contains($page, $religious))
            ->toBeFalse("الصفحة العامة تحمل نصاً دينياً: {$religious}");
    }
});

it('gives every saying its attribution', function () {
    $sayings = LearningSayings::all();

    expect($sayings)->not->toBeEmpty();

    foreach ($sayings as $saying) {
        expect(trim($saying['text']))->not->toBe('')
            ->and(trim($saying['source']))->not->toBe('')
            // No grading: a saying is attributed, not authenticated, and the
            // pill that used to carry «صحيح» went with the hadiths.
            ->and($saying)->not->toHaveKey('grade');
    }
});

it('does not always show the same one', function () {
    $seen = collect(range(1, 60))->map(fn () => LearningSayings::random()['text'])->unique();

    expect($seen->count())->toBeGreaterThan(1);
});

it('describes the organisation the way it configures itself', function () {
    config(['brand.tagline' => 'منصة تعليمية']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('منصة تعليمية')
        ->assertDontSee('لتحفيظ القرآن الكريم');
});

it('carries the same saying treatment onto the sign-in page', function () {
    // The sign-in page is the same front door, and it kept the verse and the
    // line drawings after the portal itself dropped them.
    $response = $this->get(route('login'))->assertOk();

    $response->assertDontSee('﴿', false)
        ->assertSee(config('brand.tagline'))
        ->assertSee('«', false);
});

it('puts the hadith above the sign-in on a phone', function () {
    // The hero is two columns on a wide screen and one on a phone, and the
    // text column was first — so a visitor arriving on his phone met the
    // headline, the buttons and the help links, and had to scroll past all
    // three before the hadith. Order is what decides it, not source position.
    $html = $this->get(route('home'))->assertSuccessful()->getContent();

    $hadith = strpos($html, 'order-1 lg:order-2');
    $signIn = strpos($html, 'order-2 lg:order-1');

    expect($hadith)->not->toBeFalse();
    expect($signIn)->not->toBeFalse();

    // And the wide screen keeps the arrangement it had: text first, medallion
    // beside it — which is what the `lg:` half of each pair says.
    expect($html)->toContain('lg:order-1')->toContain('lg:order-2');
});
