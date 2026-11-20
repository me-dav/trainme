<x-layout :title="'Bank soal · '.$module->title">
    <a href="{{ route('modules.show', $module) }}" class="text-sm text-zinc-400 hover:text-zinc-200">← {{ $module->title }}</a>

    <div class="mt-4 mb-8">
        <h1 class="text-2xl font-semibold">Bank soal</h1>
        <p class="mt-1 text-sm text-zinc-400">
            Semua soal beserta kunci, untuk mereview isi modul. Hindari dibuka sebelum latihan kalau ingin benar-benar menguji diri.
        </p>
    </div>

    @foreach ($tiers as $tier)
        @php($questions = $questionsByTier->get($tier->value, collect()))
        <section class="mb-10">
            <h2 class="mb-3 text-lg font-semibold">{{ $tier->label() }} <span class="text-sm font-normal text-zinc-500">· {{ $questions->count() }} soal</span></h2>

            @forelse ($questions as $question)
                <details class="card group mb-3">
                    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3">
                        <span class="font-mono text-xs text-zinc-500">{{ $question->key }}</span>
                        <span class="badge bg-zinc-800 text-zinc-400">{{ $question->type->label() }}</span>
                        <span class="min-w-0 flex-1 truncate text-sm">{{ Str::of($question->prompt)->replaceMatches('/[`*_]/', '')->limit(100) }}</span>
                    </summary>
                    <div class="space-y-4 border-t border-zinc-800 px-4 py-4">
                        <x-question-prompt :question="$question" />
                        @if ($question->checklist())
                            <div>
                                <p class="mb-1 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Checklist penilaian</p>
                                <ul class="space-y-1 text-sm text-zinc-300">
                                    @foreach ($question->checklist() as $item)
                                        <li>
                                            <span class="font-mono text-xs {{ $item['forbid'] ? 'text-red-400' : 'text-zinc-500' }}">{{ $item['forbid'] ? 'larang' : ($item['required'] ? 'wajib' : 'poin') }}</span>
                                            {{ $item['label'] }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <x-answer-reveal :question="$question" />
                    </div>
                </details>
            @empty
                <p class="text-sm text-zinc-500">Belum ada soal.</p>
            @endforelse
        </section>
    @endforeach
</x-layout>
