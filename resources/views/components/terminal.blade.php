@props(['title' => null, 'hint' => null])

{{-- A terminal window: always dark, in both themes, like a real terminal. --}}
<div {{ $attributes->class('term') }}>
    <div class="term-bar">
        <span class="term-dots" aria-hidden="true"><span></span><span></span><span></span></span>
        <span class="term-title">{{ $title ?? (auth()->user()?->shellName() ?? 'user').'@trainme: ~' }}</span>
        @if ($hint)
            <span class="term-hint">{{ $hint }}</span>
        @endif
    </div>
    <div class="term-body">
        {{ $slot }}
    </div>
</div>
