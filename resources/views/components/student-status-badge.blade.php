@props([
    /** The stored `users.status` (or a history row's status). */
    'status',
    'size' => null,
])

@php
    use App\Support\StudentStatus;
@endphp

{{-- One badge for a student's status, so the same student reads the same word,
     in the same colour, on every screen. --}}
<flux:badge :color="StudentStatus::colorOf($status)" :size="$size" {{ $attributes }}>{{ StudentStatus::labelOf($status) }}</flux:badge>
