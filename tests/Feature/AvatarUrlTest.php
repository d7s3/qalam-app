<?php

use App\Models\Manager;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;

/**
 * Avatar URLs used to be built from APP_URL. When that variable disagreed with
 * the address actually serving the site — still http, still localhost, the
 * wrong domain — every uploaded picture rendered as a broken image, and an
 * http image on an https page is blocked outright. `storage:link` was fine
 * throughout, which made it look like a storage problem.
 */
it('builds avatar urls relative to whatever origin serves the page', function () {
    $manager = Manager::factory()->create(['avatar_path' => 'avatars/manager.webp']);

    expect($manager->avatarUrl())->toBe('/storage/avatars/manager.webp');
});

it('does not bake a host or scheme into the public disk url', function () {
    config(['app.url' => 'http://a-stale-value.test']);

    $url = Storage::disk('public')->url('avatars/any.webp');

    expect($url)->toStartWith('/')
        ->and($url)->not->toContain('a-stale-value.test')
        ->and($url)->not->toContain('http://')
        ->and($url)->not->toContain('https://');
});

it('has no avatar url when no picture was uploaded', function () {
    expect(Student::factory()->create(['avatar_path' => null])->avatarUrl())->toBeNull();
});

it('serves an uploaded avatar from the public disk', function () {
    Storage::fake('public');

    $student = Student::factory()->create(['avatar_path' => 'avatars/student.webp']);
    Storage::disk('public')->put('avatars/student.webp', 'binary');

    expect(Storage::disk('public')->exists($student->avatar_path))->toBeTrue()
        ->and($student->avatarUrl())->toContain('avatars/student.webp');
});
