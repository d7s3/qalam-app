<?php

use App\Support\PageTours;
use Illuminate\Support\Facades\Route;

it('names only pages that exist, each step saying something', function () {
    foreach (PageTours::all() as $route => $steps) {
        expect(Route::has($route))->toBeTrue("no route {$route}");
        foreach ($steps as $step) {
            expect($step['text'])->not->toBeEmpty();
        }
    }
});

it('gives every page in a student\'s menu a tour', function () {
    $toured = array_keys(PageTours::all());

    foreach (['dashboard', 'my-day', 'motivations', 'self-program', 'self-program-record', 'plan', 'hifz', 'review', 'exams', 'calendar', 'attendance', 'reports', 'messages', 'settings'] as $page) {
        expect($toured)->toContain("student.{$page}");
    }
});
