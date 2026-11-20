@props(['status'])

@php
    $tier = $status['tier'];
@endphp

<span @class([
    'badge font-mono',
    'bg-emerald-500/15 text-emerald-300' => $status['passed'],
    'bg-zinc-800 text-zinc-300' => ! $status['passed'] && $status['unlocked'],
    'bg-zinc-900 text-zinc-600' => ! $status['unlocked'],
]) title="{{ $tier->label() }}">
    @if (! $status['unlocked'])
        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
    @endif
    {{ $tier->value }}
    @if ($status['best_score'] !== null)
        <span class="opacity-80">{{ (int) round($status['best_score']) }}%</span>
    @endif
</span>
