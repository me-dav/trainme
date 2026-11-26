@use('App\Enums\QuestionType')

<x-layout :title="$attempt->module->title.' · '.$attempt->tier->label()">
    @php
        $total = $attempt->questionCount();
        $number = $attempt->current_index + 1;
        $shellPrompt = auth()->user()->shellName().'@trainme:~$';
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
            <div class="h-full bg-accent-500 transition-all" style="width: {{ $total > 0 ? round(($attempt->current_index + ($answer ? 1 : 0)) / $total * 100) : 0 }}%"></div>
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
                    @if ($question->type === QuestionType::Scenario)
                        <x-terminal hint="Ctrl+Enter untuk kirim">
                            <p class="term-muted mb-2"># Tulis perintah/langkahmu, satu per baris. Komentar # boleh.</p>
                            <label for="answer" class="sr-only">Perintah dan langkah, satu per baris</label>
                            <div class="term-multiline">
                                <div class="term-gutter" data-terminal-gutter aria-hidden="true">$</div>
                                <textarea id="answer" name="answer" rows="9" required autofocus data-submit-shortcut data-terminal-lines
                                          wrap="off" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                                          class="term-field" placeholder="# diagnosis&#10;...&#10;# perbaikan&#10;...">{{ old('answer') }}</textarea>
                            </div>
                        </x-terminal>
                    @else
                        <x-terminal hint="Enter untuk kirim">
                            <label for="answer" class="term-line">
                                <span class="term-prompt">{{ $question->type === QuestionType::Command ? $shellPrompt : 'jawaban>' }}</span>
                                <span class="sr-only">{{ $question->type === QuestionType::Command ? 'Perintah' : 'Jawaban' }}</span>
                                <input id="answer" name="answer" type="text" value="{{ old('answer') }}" required autofocus
                                       autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                                       class="term-field" placeholder="{{ $question->type === QuestionType::Command ? 'ketik perintahnya…' : 'jawaban singkat…' }}">
                            </label>
                        </x-terminal>
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
                {{-- The answer as it would look in the terminal: the input echoed, then the checker's verdict. --}}
                <x-terminal class="mt-6" role="status">
                    @if ($skipped)
                        <div class="term-line"><span class="term-prompt">{{ $shellPrompt }}</span><span class="term-muted">^C</span></div>
                    @elseif ($question->type === QuestionType::Scenario)
                        @foreach (preg_split('/\R/', rtrim($answer->answer)) as $line)
                            <div class="term-line"><span class="term-prompt">$</span><span class="term-output">{{ $line }}</span></div>
                        @endforeach
                    @else
                        <div class="term-line">
                            <span class="term-prompt">{{ $question->type === QuestionType::Command ? $shellPrompt : 'jawaban>' }}</span>
                            <span class="term-output">{{ $answer->answer }}</span>
                        </div>
                    @endif
                    <p @class(['mt-2 font-semibold', 'term-ok' => $answer->is_correct, 'term-skip' => $skipped, 'term-bad' => ! $answer->is_correct && ! $skipped])>
                        @if ($answer->is_correct)
                            ✓ Benar
                        @elseif ($skipped)
                            ↷ Dilewati
                        @else
                            ✗ Belum tepat
                        @endif
                    </p>
                </x-terminal>

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
