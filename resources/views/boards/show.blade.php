<x-layout :title="$board->title" :wide="true">
    @php
        $importErrors = session('importErrors');
        $importWarnings = session('importWarnings', []);
        $openImport = $importErrors !== null || $errors->import->any();
        $openAiImport = $errors->aiImport->any();
    @endphp

    <nav class="mb-4 text-sm text-zinc-500">
        <a href="{{ route('dashboard') }}" class="hover:text-zinc-300">Board</a>
        <span class="mx-1">/</span>
        <span class="text-zinc-300">{{ $board->title }}</span>
    </nav>

    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <p class="font-mono text-xs text-zinc-500">~/{{ $board->slug }}</p>
            <h1 class="mt-1 text-2xl font-semibold">{{ $board->title }}</h1>
            @if ($board->description)
                <p class="mt-1 max-w-2xl text-sm text-zinc-400">{{ $board->description }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <button type="button" class="btn btn-ghost" data-dialog-open="board-edit">Edit board</button>
            @if ($aiEnabled)
                <button type="button" class="btn btn-ghost btn-ai" data-dialog-open="import-ai">✦ Impor dari PDF/Word</button>
            @endif
            <button type="button" class="btn btn-primary" data-dialog-open="import-module">+ Impor modul</button>
        </div>
    </div>

    @if ($board->modules->isEmpty())
        <div class="card p-10 text-center">
            <p class="font-mono text-accent-400">$ ls ~/{{ $board->slug }}/</p>
            <p class="mt-2 text-zinc-400">Board ini belum punya modul. Unduh template, buat modul dengan AI, lalu unggah di sini.</p>
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                @if ($aiEnabled)
                    <button type="button" class="btn btn-ghost btn-ai" data-dialog-open="import-ai">✦ Impor dari PDF/Word</button>
                @endif
                <button type="button" class="btn btn-primary" data-dialog-open="import-module">+ Impor modul</button>
            </div>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($board->modules as $module)
                <a href="{{ route('modules.show', $module) }}" class="card group block p-5 transition hover:border-zinc-600">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-mono text-xs text-zinc-500">{{ str_pad((string) $module->position, 2, '0', STR_PAD_LEFT) }} · v{{ $module->version }}</p>
                            <h2 class="mt-1 font-semibold group-hover:text-accent-300">{{ $module->title }}</h2>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1">
                            @if (in_array($module->id, $activeModuleIds, true))
                                <span class="badge bg-sky-500/15 text-sky-300">tes berjalan</span>
                            @endif
                            @unless ($module->isVerified())
                                <span class="badge bg-amber-500/15 text-amber-300">draft</span>
                            @endunless
                        </div>
                    </div>
                    @if ($module->summary)
                        <p class="mt-2 text-sm text-zinc-400">{{ $module->summary }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($statuses[$module->id] as $status)
                            <x-tier-pill :status="$status" />
                        @endforeach
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Import: template → generate with AI → upload --}}
    <x-modal id="import-module" title="Impor modul ke {{ $board->title }}" :open="$openImport" :wide="true">
        @if ($importErrors !== null)
            <div class="mb-6 rounded-lg border border-red-500/40 bg-red-500/10 p-4" role="alert">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-red-300">File ditolak, {{ count($importErrors) }} masalah</p>
                    <button type="button" class="btn btn-ghost px-3 py-1 text-xs" data-copy="import-errors-text">Salin error untuk AI</button>
                </div>
                <ul class="mt-2 max-h-48 list-disc space-y-1 overflow-y-auto pl-5 text-sm text-red-200">
                    @foreach ($importErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <p class="mt-3 text-xs text-red-200/80">Tempel error ini ke AI yang membuat file, minta ia memperbaiki dan mengirim ulang file lengkapnya.</p>
                <textarea id="import-errors-text" class="sr-only" readonly tabindex="-1" aria-hidden="true">File modul ditolak validator devops-dojo. Perbaiki semua masalah berikut tanpa mengubah bagian lain yang sudah benar, lalu kirim ulang file .md lengkapnya dalam satu code block:
@foreach ($importErrors as $error)
- {{ $error }}
@endforeach</textarea>
                @if ($importWarnings !== [])
                    <details class="mt-3 text-sm text-amber-200">
                        <summary class="cursor-pointer text-amber-300">{{ count($importWarnings) }} peringatan</summary>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            @foreach ($importWarnings as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        <ol class="space-y-6">
            <li class="flex gap-3">
                <span class="step-number">1</span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium">Unduh template</p>
                    <p class="mt-0.5 text-sm text-zinc-400">
                        File <code class="font-mono text-accent-300">.md</code> (Markdown + frontmatter YAML) berisi kerangka modul
                        dan contoh satu soal untuk tiap tipe, lengkap dengan aturannya.
                    </p>
                    <a href="{{ route('boards.template', $board) }}" class="btn btn-ghost mt-3">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                        template-modul-{{ $board->slug }}.md
                    </a>
                </div>
            </li>

            <li class="flex gap-3">
                <span class="step-number">2</span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium">Generate dengan AI</p>
                    <p class="mt-0.5 text-sm text-zinc-400">
                        Buka Claude atau AI lain, lampirkan template tadi, tempel prompt di bawah, lalu ganti
                        <code class="font-mono text-accent-300">[TULIS TOPIKNYA DI SINI]</code>. Simpan hasilnya sebagai file <code class="font-mono">.md</code>.
                    </p>
                    <div class="mt-3 overflow-hidden rounded-lg border border-zinc-800">
                        <div class="flex items-center justify-between border-b border-zinc-800 bg-zinc-950 px-3 py-1.5">
                            <span class="font-mono text-xs text-zinc-500">prompt</span>
                            <button type="button" class="text-xs text-accent-400 hover:text-accent-300" data-copy="ai-prompt">Salin prompt</button>
                        </div>
                        <textarea id="ai-prompt" readonly rows="8" spellcheck="false"
                                  class="block w-full resize-y bg-black/60 px-3 py-2 font-mono text-xs leading-relaxed text-zinc-300 focus:outline-none">@include('boards.partials.ai-prompt')</textarea>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500">
                        Validator hanya memeriksa konsistensi penilaian, bukan kebenaran isi. Modul hasil unggahan masuk sebagai
                        <span class="text-amber-300">draft</span> sampai kamu cek dan tandai terverifikasi.
                    </p>
                </div>
            </li>

            <li class="flex gap-3">
                <span class="step-number">3</span>
                <div class="min-w-0 flex-1">
                    <p class="font-medium">Unggah di sini</p>
                    <form method="POST" action="{{ route('import.store', $board) }}" enctype="multipart/form-data" class="mt-3 space-y-4">
                        @csrf
                        <div>
                            <label for="import-file" class="sr-only">File modul (.md)</label>
                            <input id="import-file" name="file" type="file" accept=".md,.markdown"
                                   class="block w-full text-sm text-zinc-400 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-4 file:py-2 file:text-zinc-200 hover:file:bg-zinc-700">
                            <p class="mt-1 text-xs text-zinc-500">Format .md, maksimal 1 MB, satu file = satu modul.</p>
                            @error('file', 'import')
                                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <details @if (old('content')) open @endif>
                            <summary class="cursor-pointer text-sm text-zinc-400 hover:text-zinc-200">atau tempel isi file langsung</summary>
                            <label for="import-content" class="sr-only">Isi modul</label>
                            <textarea id="import-content" name="content" rows="8" spellcheck="false" class="input mt-2 font-mono text-xs"
                                      placeholder="---&#10;format: 1&#10;id: {{ $board->slug }}-...&#10;...">{{ old('content') }}</textarea>
                            @error('content', 'import')
                                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </details>

                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
                            <button type="submit" class="btn btn-primary">Validasi & pratinjau</button>
                        </div>
                    </form>
                </div>
            </li>
        </ol>
    </x-modal>

    @if ($aiEnabled)
        {{-- Import with AI: PDF/Word → module file, generated in the background --}}
        <x-modal id="import-ai" title="Impor dari PDF/Word ke {{ $board->title }}" :open="$openAiImport" :wide="true">
            <p class="text-sm text-zinc-400">
                Unggah materi yang sudah ada (PDF, termasuk hasil scan, atau Word). AI akan menyusunnya menjadi satu modul lengkap
                dengan soal tier A, B, dan C, lalu memeriksanya dengan validator yang sama seperti unggahan manual.
            </p>
            <form method="POST" action="{{ route('ai-imports.store', $board) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="ai-document" class="sr-only">Dokumen (PDF atau Word)</label>
                    <input id="ai-document" name="document" type="file" accept=".pdf,.docx" required
                           class="block w-full text-sm text-zinc-400 file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-4 file:py-2 file:text-zinc-200 hover:file:bg-zinc-700">
                    <p class="mt-1 text-xs text-zinc-500">
                        PDF atau .docx, maksimal {{ intdiv((int) config('services.llm.max_upload_kb'), 1024) }} MB. Satu file = satu modul; untuk buku, potong per bab.
                    </p>
                    @error('document', 'aiImport')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>
                <p class="text-xs text-zinc-500">
                    Proses berjalan di latar belakang, biasanya beberapa menit. Hasilnya masuk ke pratinjau dulu dan disimpan sebagai
                    <span class="text-amber-300">draft</span>; cek isinya sebelum menandai terverifikasi.
                </p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Proses dengan AI</button>
                </div>
            </form>
        </x-modal>
    @endif

    <x-modal id="board-edit" title="Edit board" :open="$errors->boardEdit->any() || $errors->boardDelete->any()">
        <form method="POST" action="{{ route('boards.update', $board) }}" class="space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label for="edit-title" class="label">Nama board</label>
                <input id="edit-title" name="title" type="text" maxlength="60" required value="{{ old('title', $board->title) }}" class="input">
                @error('title', 'boardEdit')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="edit-description" class="label">Deskripsi <span class="font-normal text-zinc-500">(opsional)</span></label>
                <textarea id="edit-description" name="description" rows="2" maxlength="300" class="input">{{ old('description', $board->description) }}</textarea>
                @error('description', 'boardEdit')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <p class="text-xs text-zinc-500">
                Slug <code class="font-mono text-zinc-300">{{ $board->slug }}</code> tetap, karena dipakai di URL dan
                di <code class="font-mono">course.slug</code> file modul untuk impor lewat terminal.
            </p>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>

        <div class="mt-6 border-t border-zinc-800 pt-4">
            @if ($board->modules->isEmpty())
                <form method="POST" action="{{ route('boards.destroy', $board) }}" data-confirm="Hapus board {{ $board->title }}?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-400 hover:text-red-300">Hapus board</button>
                </form>
            @else
                <details @if ($errors->boardDelete->any()) open @endif>
                    <summary class="cursor-pointer text-sm text-red-400 hover:text-red-300">Hapus board…</summary>
                    <form method="POST" action="{{ route('boards.destroy', $board) }}" class="mt-3 space-y-3 rounded-lg border border-red-500/40 bg-red-500/10 p-4">
                        @csrf
                        @method('DELETE')
                        <p class="text-sm text-red-200">
                            Board ini dan <strong>{{ $board->modules->count() }} modul</strong> di dalamnya akan dihapus permanen,
                            termasuk semua soal dan riwayat tes. Tindakan ini tidak bisa dibatalkan.
                        </p>
                        <div>
                            <label for="delete-confirm" class="label">Ketik <code class="font-mono text-red-300">{{ $board->slug }}</code> untuk konfirmasi</label>
                            <input id="delete-confirm" name="confirm" type="text" autocomplete="off" spellcheck="false" required class="input font-mono">
                            @error('confirm', 'boardDelete')
                                <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-danger">Hapus board dan semua modulnya</button>
                    </form>
                </details>
            @endif
        </div>
    </x-modal>
</x-layout>
