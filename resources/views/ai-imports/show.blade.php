<x-layout title="Impor dengan AI">
    @php
        $status = $aiImport->status;
    @endphp

    @if ($status->isRunning())
        @push('head')
            <meta http-equiv="refresh" content="5">
        @endpush
    @endif

    <a href="{{ route('boards.show', $aiImport->course) }}" class="text-sm text-zinc-400 hover:text-zinc-200">← Kembali ke board {{ $aiImport->course->title }}</a>

    <h1 class="mt-4 text-2xl font-semibold">Impor dengan AI</h1>
    <p class="mt-1 font-mono text-sm text-zinc-400">{{ $aiImport->original_name }} → board {{ $aiImport->course->title }}</p>

    @if ($status->isRunning())
        <div class="card mt-6 p-6" role="status" aria-live="polite">
            <div class="flex items-center gap-3">
                <span class="relative flex h-3 w-3" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                </span>
                <p class="font-semibold">
                    @if ($status === \App\Enums\AiImportStatus::Pending)
                        Menunggu giliran diproses…
                    @else
                        {{ $aiImport->progress ?? 'AI mulai memproses dokumen' }}
                    @endif
                </p>
            </div>
            <p class="mt-3 text-sm text-zinc-400">
                Halaman ini memeriksa ulang otomatis setiap 5 detik dan pindah ke pratinjau begitu modul selesai.
                Kamu boleh menutup halaman ini dan kembali lewat URL yang sama.
            </p>
            @if ($aiImport->started_at)
                <p class="mt-2 font-mono text-xs text-zinc-500">mulai {{ $aiImport->started_at->diffForHumans() }}</p>
            @endif
        </div>
    @else
        <div class="mt-6 rounded-lg border border-red-500/40 bg-red-500/10 p-4" role="alert">
            <p class="font-semibold text-red-300">Impor gagal</p>
            <p class="mt-1 text-sm text-red-200">{{ $aiImport->error }}</p>
            @if ($aiImport->validation_errors)
                <ul class="mt-3 max-h-60 list-disc space-y-1 overflow-y-auto pl-5 text-sm text-red-200">
                    @foreach ($aiImport->validation_errors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($aiImport->result)
            <div class="card mt-4 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold">Hasil terakhir dari AI</p>
                    <button type="button" class="btn btn-ghost px-3 py-1 text-xs" data-copy="ai-import-result">Salin hasil</button>
                </div>
                <p class="mt-1 text-xs text-zinc-500">
                    Sebagian besar isinya biasanya sudah benar. Salin, perbaiki bagian yang disebut di atas, lalu tempel lewat
                    <span class="text-zinc-300">Impor modul → atau tempel isi file langsung</span> di board.
                </p>
                <textarea id="ai-import-result" readonly rows="14" spellcheck="false"
                          class="input mt-3 font-mono text-xs">{{ $aiImport->result }}</textarea>
            </div>
        @endif

        <a href="{{ route('boards.show', $aiImport->course) }}" class="btn btn-primary mt-6">Kembali ke board</a>
    @endif
</x-layout>
