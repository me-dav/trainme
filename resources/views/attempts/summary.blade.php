<x-layout :title="'Hasil · '.$attempt->module->title">
    @php
        $passed = $attempt->passed();
        $correct = $answers->where('is_correct', true)->count();
    @endphp

    <nav class="text-sm text-zinc-500">
        <a href="{{ route('boards.show', $attempt->module->course) }}" class="hover:text-zinc-300">{{ $attempt->module->course->title }}</a>
        <span class="mx-1">/</span>
        <a href="{{ route('modules.show', $attempt->module) }}" class="hover:text-zinc-300">{{ $attempt->module->title }}</a>
    </nav>

    <section @class(['card mt-4 p-8 text-center', 'border-emerald-500/40' => $passed, 'border-red-500/30' => ! $passed])>
        <p class="text-sm text-zinc-400">{{ $attempt->tier->label() }}</p>
        <p @class(['mt-2 font-mono text-5xl font-semibold', 'text-emerald-400' => $passed, 'text-red-400' => ! $passed])>
            {{ (int) round($attempt->score) }}%
        </p>
        <p class="mt-2 text-zinc-300">
            {{ $correct }} dari {{ $answers->count() }} benar ·
            {{ $passed ? 'Lulus' : 'Belum lulus' }} (minimal {{ config('dojo.pass_score') }}%)
        </p>

        @if ($passed && $nextTier && $nextTierUnlocked)
            <p class="mt-4 text-sm text-emerald-300">{{ $nextTier->label() }} terbuka.</p>
        @elseif (! $passed)
            <p class="mt-4 text-sm text-zinc-400">Soal yang salah akan diprioritaskan muncul lagi di sesi berikutnya.</p>
        @endif

        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a href="{{ route('modules.show', $attempt->module) }}" class="btn btn-ghost">← Kembali ke materi</a>
            <form method="POST" action="{{ route('attempts.store', [$attempt->module, $attempt->tier]) }}">
                @csrf
                <button type="submit" class="btn btn-ghost">Ulangi {{ $attempt->tier->value }}</button>
            </form>
            @if ($passed && $nextTier && $nextTierUnlocked)
                <form method="POST" action="{{ route('attempts.store', [$attempt->module, $nextTier]) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Mulai {{ $nextTier->label() }}</button>
                </form>
            @endif
        </div>
    </section>

    <section class="mt-8 space-y-3">
        <h2 class="text-sm font-semibold tracking-wider text-zinc-400 uppercase">Rincian</h2>
        @foreach ($answers as $answer)
            <details class="card group">
                <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                    <span @class(['font-mono text-xs', 'text-emerald-400' => $answer->is_correct, 'text-red-400' => ! $answer->is_correct])>
                        {{ $answer->is_correct ? '[✓]' : '[✗]' }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-sm">{{ Str::of($answer->question->prompt)->replaceMatches('/[`*_]/', '')->limit(110) }}</span>
                    <span class="text-xs text-zinc-500 group-open:hidden">detail</span>
                </summary>
                <div class="space-y-4 border-t border-zinc-800 px-4 py-4">
                    <x-question-prompt :question="$answer->question" />
                    <div>
                        <p class="mb-1 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Jawabanmu</p>
                        <pre class="terminal py-2 whitespace-pre-wrap">{{ trim($answer->answer) === '' ? '(dilewati)' : $answer->answer }}</pre>
                    </div>
                    <x-checklist-result :feedback="$answer->feedback ?? []" />
                    <x-answer-reveal :question="$answer->question" />
                </div>
            </details>
        @endforeach
    </section>
</x-layout>
