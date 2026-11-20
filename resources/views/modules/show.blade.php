<x-layout :title="$module->title" :wide="true">
    <nav class="mb-4 text-sm text-zinc-500">
        <a href="{{ route('dashboard') }}" class="hover:text-zinc-300">Board</a>
        <span class="mx-1">/</span>
        <a href="{{ route('boards.show', $module->course) }}" class="hover:text-zinc-300">{{ $module->course->title }}</a>
        <span class="mx-1">/</span>
        <span class="text-zinc-300">{{ $module->title }}</span>
    </nav>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <article class="panel min-w-0">
            <div class="mb-6 flex flex-wrap items-center gap-2 text-xs">
                <span class="badge bg-zinc-800 font-mono text-zinc-300">v{{ $module->version }}</span>
                @if ($module->isVerified())
                    <span class="badge bg-emerald-500/15 text-emerald-300">verified</span>
                @else
                    <span class="badge bg-amber-500/15 text-amber-300">draft — belum diverifikasi</span>
                @endif
                @if ($module->content_updated_on)
                    <span class="text-zinc-500">diperbarui {{ $module->content_updated_on }}</span>
                @endif
                <form method="POST" action="{{ route('modules.status', $module) }}" class="ml-auto"
                      @if (! $module->isVerified()) data-confirm="Tandai terverifikasi? Pastikan command, flag, dan sumbernya sudah kamu cek." @endif>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $module->isVerified() ? 'draft' : 'verified' }}">
                    <button type="submit" class="text-zinc-500 hover:text-zinc-300">
                        {{ $module->isVerified() ? 'Kembalikan ke draft' : 'Tandai terverifikasi' }}
                    </button>
                </form>
            </div>

            <div class="prose-dojo">
                {!! $document['html'] !!}
            </div>

            @if ($module->sources)
                <section class="mt-12 border-t border-zinc-800 pt-6" id="sumber">
                    <h2 class="mb-3 text-sm font-semibold tracking-wider text-zinc-400 uppercase">Sumber</h2>
                    <ul class="space-y-1.5 text-sm">
                        @foreach ($module->sources as $source)
                            <li>
                                <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:underline">{{ $source['title'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </article>

        <aside class="space-y-6 lg:sticky lg:top-20 lg:self-start">
            <section class="card p-4">
                <h2 class="mb-3 text-sm font-semibold">Latihan</h2>
                <div class="space-y-3">
                    @foreach ($statuses as $status)
                        @php($tier = $status['tier'])
                        <div @class(['rounded-lg border p-3', 'border-emerald-500/40' => $status['passed'], 'border-zinc-800' => ! $status['passed']])>
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-medium">{{ $tier->label() }}</p>
                                @if ($status['best_score'] !== null)
                                    <span @class(['font-mono text-xs', 'text-emerald-400' => $status['passed'], 'text-zinc-400' => ! $status['passed']])>
                                        terbaik {{ (int) round($status['best_score']) }}%
                                    </span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-zinc-500">
                                {{ $tier->description() }}
                                {{ $status['quiz_size'] }} dari {{ $status['question_count'] }} soal per sesi.
                            </p>

                            @if ($status['question_count'] === 0)
                                <p class="mt-2 text-xs text-zinc-600">Belum ada soal.</p>
                            @elseif (! $status['unlocked'])
                                <p class="mt-2 flex items-center gap-1 text-xs text-zinc-500">
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 0 0-4.5 4.5V9H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2h-.5V5.5A4.5 4.5 0 0 0 10 1Zm3 8V5.5a3 3 0 1 0-6 0V9h6Z" clip-rule="evenodd"/></svg>
                                    Lulus {{ $tier->previous()?->value }} ≥ {{ config('dojo.pass_score') }}% untuk membuka.
                                </p>
                            @else
                                <form method="POST" action="{{ route('attempts.store', [$module, $tier]) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="btn btn-primary w-full py-1.5">
                                        @if ($status['in_progress'])
                                            Lanjutkan ({{ $status['in_progress']->current_index }}/{{ $status['in_progress']->questionCount() }})
                                        @elseif ($status['attempt_count'] > 0)
                                            Ulangi
                                        @else
                                            Mulai
                                        @endif
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('modules.bank', $module) }}" class="mt-4 block text-center text-xs text-zinc-500 hover:text-zinc-300">
                    Lihat bank soal + kunci (spoiler)
                </a>
            </section>

            @if ($document['toc'])
                <section class="card p-4">
                    <h2 class="mb-2 text-sm font-semibold">Isi materi</h2>
                    <ol class="space-y-1 text-sm">
                        @foreach ($document['toc'] as $entry)
                            <li><a href="#{{ $entry['id'] }}" class="text-zinc-400 hover:text-emerald-300">{{ $entry['title'] }}</a></li>
                        @endforeach
                    </ol>
                </section>
            @endif

            @if ($module->objectives)
                <section class="card p-4">
                    <h2 class="mb-2 text-sm font-semibold">Setelah modul ini kamu bisa</h2>
                    <ul class="list-disc space-y-1 pl-4 text-sm text-zinc-400">
                        @foreach ($module->objectives as $objective)
                            <li>{!! Str::inlineMarkdown($objective['text'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($module->tested_on)
                <section class="card p-4">
                    <h2 class="mb-2 text-sm font-semibold">Diverifikasi di</h2>
                    <ul class="space-y-1 font-mono text-xs text-zinc-400">
                        @foreach ($module->tested_on as $environment)
                            <li>{{ $environment }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</x-layout>
