<?php

use App\Models\Manager;
use App\Models\Supervisor;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function countSidebarBits(string $label, string $html): void
{
    $backdrops = substr_count($html, 'data-flux-sidebar-backdrop');
    $sidebars = preg_match_all('/<ui-sidebar\s/', $html);
    $toggles = preg_match_all('/<ui-sidebar-toggle/', $html);
    fwrite(STDERR, "\n[{$label}] ui-sidebar={$sidebars}  backdrops={$backdrops}  ui-sidebar-toggle(total)={$toggles}\n");
}

it('counts sidebar/backdrop instances per role page', function () {
    $manager = Manager::factory()->create();
    countSidebarBits('manager/dashboard', $this->actingAs($manager, 'manager')->get('/manager/dashboard')->getContent());

    $supervisor = Supervisor::factory()->create();
    countSidebarBits('supervisor/dashboard', $this->actingAs($supervisor, 'supervisor')->get('/supervisor/dashboard')->getContent());

    $teacher = Teacher::factory()->create(['is_approved' => true]);
    countSidebarBits('teacher/dashboard', $this->actingAs($teacher, 'teacher')->get('/teacher/dashboard')->getContent());
    countSidebarBits('teacher/tasmeeh', $this->actingAs($teacher, 'teacher')->get('/teacher/tasmeeh')->getContent());

    expect(true)->toBeTrue();
});
