@use('App\Enums\QuestionType')

<x-layout :title="$attempt->module->title.' · '.$attempt->tier->label()">
    @php
        $total = $attempt->questionCount();
        $number = $attempt->current_index + 1;
    @endphp

    <div class="mb-6">
        <div class="mb-2 flex items-center justify-between text-sm">
            <span class="min-w-0 truncate text-zinc-400">
                <svg class="mr-1 inline h-3.5 w-3.5 align-[-2px]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                {{ $attempt->module->title }} <span class="text-zinc-600">· materi terkunci selama tes</span>
            </span>
            <span class="shrink-0 font-mono text-zinc-400">{{ $attempt->tier->label() }} · {{ $number }}/{{ $total }}</span>
        </div>
        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-800" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $attempt->current_index }}">
            <div class="h-full bg-emerald-500 transition-all" style="width: {{ $total > 0 ? round(($attempt->current_index + ($answer ? 1 : 0)) / $total * 100) : 0 }}%"></div>
        </div>
    </div>

    @if ($question === null)
        <div class="card p-6 text-zinc-400">Soal tidak ditemukan. Akhiri tes di bawah untuk melihat hasil.</div>
    @else
        <section class="card p-6">
            <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
                <span class="badge bg-zinc-800 text-zinc-300">{{ $question->type->label() }}</span>
                @if ($objective = $attempt->module->objectiveText($question->objective))
                    <span class="text-zinc-500">{!! Str::inlineMarkdown($objective, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</span>
                @endif
            </div>

            <x-question-prompt :question="$question" />

            @if ($answer === null)
                <form method="POST" action="{{ route('attempts.answer', $attempt) }}" class="mt-6">
                    @csrf
                    @if ($question->type === QuestionType::Command)
                        <label for="answer" class="sr-only">Perintah</label>
                        <div class="flex items-center rounded-lg border border-zinc-700 bg-black/60 px-3 font-mono focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/30">
                            <span class="select-none text-emerald-500">$</span>
                            <input id="answer" name="answer" type="text" value="{{ old('answer') }}" required autofocus
                                   autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                                   class="w-full bg-transparent px-2 py-3 text-sm text-zinc-100 focus:outline-none"
                                   placeholder="ketik perintahnya…">
                        </div>
                    @elseif ($question->type === QuestionType::Text)
                        <label for="answer" class="sr-only">Jawaban</label>
                        <input id="answer" name="answer" type="text" value="{{ old('answer') }}" required autofocus
                               autocomplete="off" spellcheck="false" class="input font-mono" placeholder="jawaban singkat…">
                    @else
                        <label for="answer" class="mb-2 block text-sm text-zinc-400">Tulis perintah/langkahmu, satu per baris.</label>
                        <textarea id="answer" name="answer" rows="9" required autofocus data-submit-shortcut
                                  autocomplete="off" autocapitalize="off" spellcheck="false"
                                  class="input font-mono text-sm leading-relaxed" placeholder="# diagnosis&#10;...&#10;# perbaikan&#10;...">{{ old('answer') }}</textarea>
                        <p class="mt-1 text-xs text-zinc-500">Ctrl+Enter untuk kirim. Komentar <code>#</code> boleh.</p>
                    @endif

                    @error('answer')
                        <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="mt-4 flex items-center justify-between gap-3">
                        <button type="submit" class="btn btn-primary">Periksa</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('attempts.skip', $attempt) }}" class="mt-2 text-right">
                    @csrf
                    <button type="submit" class="text-xs text-zinc-500 hover:text-zinc-300">Tidak tahu — tampilkan jawaban</button>
                </form>
            @else
                @php
                    $skipped = trim($answer->answer) === '';
                @endphp
                <div @class([
                    'mt-6 rounded-lg border px-4 py-3',
                    'border-emerald-500/40 bg-emerald-500/10' => $answer->is_correct,
                    'border-red-500/40 bg-red-500/10' => ! $answer->is_correct,
                ]) role="status">
                    <p @class(['font-semibold', 'text-emerald-300' => $answer->is_correct, 'text-red-300' => ! $answer->is_correct])>
                        @if ($answer->is_correct)
                            Benar
                        @elseif ($skipped)
                            Dilewati
                        @else
                            Belum tepat
                        @endif
                    </p>
                    @unless ($skipped)
                        <pre class="mt-2 font-mono text-sm whitespace-pre-wrap text-zinc-300">{{ $answer->answer }}</pre>
                    @endunless
                </div>

                <div class="mt-6 space-y-6">
                    <x-checklist-result :feedback="$answer->feedback ?? []" />
                    <x-answer-reveal :question="$question" />
                </div>

                <form method="POST" action="{{ route('attempts.next', $attempt) }}" class="mt-8">
                    @csrf
                    <button type="submit" class="btn btn-primary" autofocus>
                        {{ $number < $total ? 'Soal berikutnya →' : 'Lihat hasil' }}
                    </button>
                </form>
            @endif
        </section>
    @endif

    <form method="POST" action="{{ route('attempts.finish', $attempt) }}" class="mt-6 text-center"
          data-confirm="Akhiri tes sekarang? Soal yang belum dijawab dihitung salah.">
        @csrf
        <button type="submit" class="text-sm text-zinc-500 hover:text-red-300">Akhiri tes</button>
        <p class="mt-1 text-xs text-zinc-600">Soal yang belum dijawab dihitung salah.</p>
    </form>
</x-layout>
