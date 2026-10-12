@props([
    /** ['up' => bool, 'text' => string], or null for no change worth showing. */
    'change' => null,
])

{{-- A figure's movement against the period before: green up, red down. --}}
@if ($change)
    <span dir="ltr" {{ $attributes->class([
        'inline-flex h-6 items-center gap-0.5 rounded-lg border px-2 text-xs font-semibold',
        'border-emerald-200 bg-emerald-50 text-emerald-700' => $change['up'],
        'border-red-200 bg-red-50 text-red-700' => ! $change['up'],
    ]) }}>
        {{ $change['text'] }}
        <flux:icon :icon="$change['up'] ? 'arrow-up-right' : 'arrow-down-right'" variant="micro" class="size-3.5" />
    </span>
@endif
