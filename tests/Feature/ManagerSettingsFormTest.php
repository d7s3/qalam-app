<?php

use App\Livewire\Manager\Settings;
use App\Models\Manager;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves discipline settings from the manager settings page', function () {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(Settings::class)
        ->set('absenceLimit', 4)
        ->set('latenessLimit', 6)
        ->set('calculationPeriodDays', 45)
        ->call('save')
        ->assertHasNoErrors();

    expect((int) Setting::getVal('absence_limit'))->toBe(4);
    expect((int) Setting::getVal('lateness_limit'))->toBe(6);
    expect((int) Setting::getVal('calculation_period_days'))->toBe(45);
});

it('rejects a non-numeric absence limit', function () {
    $manager = Manager::factory()->create();

    $this->actingAs($manager, 'manager');

    Livewire::test(Settings::class)
        ->set('absenceLimit', 'abc')
        ->set('latenessLimit', 5)
        ->set('calculationPeriodDays', 30)
        ->call('save')
        ->assertHasErrors('absenceLimit');
});
