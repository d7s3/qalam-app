<?php

use App\Models\Guardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * Guardian accounts made before the fix opened with their own phone. The
 * command closes those and only those, and leaves the magic link alone.
 */
it('closes the accounts that open with their own phone, and only those', function () {
    $exposed = Guardian::factory()->create(['phone' => '966501234567', 'password' => '966501234567', 'access_token' => 'family-link']);
    $exposedLocally = Guardian::factory()->create(['phone' => '966551112222', 'password' => '0551112222']);
    $own = Guardian::factory()->create(['phone' => '966509998888', 'password' => 'a-password-of-his-own']);

    $this->artisan('guardians:secure-passwords')->assertSuccessful();

    expect(Hash::check('966501234567', $exposed->fresh()->password))->toBeFalse()
        ->and(Hash::check('0551112222', $exposedLocally->fresh()->password))->toBeFalse()
        ->and(Hash::check('a-password-of-his-own', $own->fresh()->password))->toBeTrue()
        ->and($exposed->fresh()->access_token)->toBe('family-link');
});

it('only counts when asked for a dry run', function () {
    $exposed = Guardian::factory()->create(['phone' => '966501234567', 'password' => '966501234567']);

    $this->artisan('guardians:secure-passwords', ['--dry-run' => true])->assertSuccessful();

    expect(Hash::check('966501234567', $exposed->fresh()->password))->toBeTrue();
});
