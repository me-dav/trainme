@props(['id', 'title', 'open' => false, 'wide' => false])

<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if ($open) data-open-on-load @endif
        {{ $attributes->class(['modal', 'max-w-2xl' => $wide, 'max-w-md' => ! $wide]) }}>
    <div class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-zinc-800 bg-zinc-900 px-5 py-4">
        <h2 id="{{ $id }}-title" class="font-semibold">{{ $title }}</h2>
        <button type="button" data-dialog-close aria-label="Tutup"
                class="rounded-md p-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200">
            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
        </button>
    </div>
    <div class="px-5 py-5">
        {{ $slot }}
    </div>
</dialog>
