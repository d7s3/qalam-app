{{--
    The navigation of whoever is reading, not of whoever the page was written for.

    Every page used to name its own role's sidebar — `<x-role-sidebar />`
    — which was true while a page belonged to one role. It stopped being true when
    seniority began to include: a supervisor opening a page written for teachers
    would have been handed the teacher's navigation and no way back to his own.
--}}
@props(['role' => null])

@php
    $navRole = $role ?? \App\Support\Scope::resolveRole();
    $nav = $navRole.'.sidebar-nav';
@endphp

@if (view()->exists($nav))
    @include($nav)
@endif
