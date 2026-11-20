@use('App\Enums\Tier')

<x-layout title="Pratinjau impor">
    @php
        $report = $analysis->report;
        $definition = $report->definition;
        $diff = $analysis->diff;
    @endphp

    <a href="{{ route('boards.show', $board) }}" class="text-sm text-zinc-400 hover:text-zinc-200">← Kembali ke board {{ $board->title }}</a>

    @if (! $analysis->isValid())
        <div class="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 p-4" role="alert">
            <p class="font-semibold text-red-300">File tidak lagi valid</p>
            <ul class="mt-2 list-disc pl-5 text-sm text-red-200">
                @foreach ($report->errors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <h1 class="mt-4 text-2xl font-semibold">{{ $definition['title'] }}</h1>
        <p class="mt-1 font-mono text-sm text-zinc-400">
            board {{ $board->title }} · {{ $definition['id'] }} ·
            @if ($diff['is_new_module'])
                modul baru v{{ $definition['version'] }}
            @else
                v{{ $diff['previous_version'] }} → v{{ $definition['version'] }}
            @endif
        </p>
        <p class="mt-2"><span class="badge bg-amber-500/15 text-amber-300">disimpan sebagai draft</span></p>

        <div class="mt-6 grid gap-3 sm:grid-cols-4">
            @foreach ([['Baru', $diff['new'], 'text-emerald-300'], ['Berubah', $diff['changed'], 'text-sky-300'], ['Diarsipkan', $diff['removed'], 'text-amber-300'], ['Tetap', $diff['unchanged'], 'text-zinc-400']] as [$label, $keys, $color])
                <div class="card p-4">
                    <p class="text-xs text-zinc-500">{{ $label }}</p>
                    <p class="font-mono text-2xl {{ $color }}">{{ count($keys) }}</p>
                    @if ($keys !== [] && $label !== 'Tetap')
                        <p class="mt-1 truncate font-mono text-xs text-zinc-500" title="{{ implode(', ', $keys) }}">{{ implode(', ', $keys) }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="card mt-4 p-4">
            <p class="mb-2 text-sm font-semibold">Soal per tier</p>
            <div class="flex flex-wrap gap-4 text-sm">
                @foreach (Tier::cases() as $tier)
                    @php($count = collect($definition['questions'])->where('tier', $tier->value)->count())
                    <span class="text-zinc-300">{{ $tier->label() }}: <span class="font-mono">{{ $count }}</span>
                        <span class="text-zinc-500">(kuis {{ min($count, $definition['quiz'][$tier->value] ?? config('dojo.quiz_sizes.'.$tier->value)) }})</span>
                    </span>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-emerald-400">✓ Semua jawaban kunci dan contoh `tests` lolos self-test penilai.</p>
        </div>

        @if ($diff['removed'] !== [])
            <p class="mt-4 text-sm text-amber-300">
                Soal yang diarsipkan tidak muncul lagi di kuis, tapi riwayat jawabanmu untuk soal itu tetap tersimpan.
            </p>
        @endif

        @if ($report->warnings !== [])
            <div class="mt-4 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4">
                <p class="font-semibold text-amber-300">Peringatan ({{ count($report->warnings) }})</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-200">
                    @foreach ($report->warnings as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('import.confirm', $token) }}" class="mt-8 flex flex-wrap items-center gap-3">
            @csrf
            <button type="submit" class="btn btn-primary" @disabled(! $analysis->hasChanges())>
                {{ $diff['is_new_module'] ? 'Impor modul' : 'Terapkan perubahan' }}
            </button>
            <a href="{{ route('boards.show', $board) }}" class="btn btn-ghost">Batal</a>
            @unless ($analysis->hasChanges())
                <span class="text-sm text-zinc-400">Tidak ada perubahan dibanding versi yang sudah diimpor.</span>
            @endunless
        </form>
    @endif
</x-layout>
