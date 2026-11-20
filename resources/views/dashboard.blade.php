<x-layout title="Board" :wide="true">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Halo, {{ auth()->user()->name }}</h1>
            <p class="mt-1 text-sm text-zinc-400">
                Satu board untuk satu topik (Linux, Docker, ...). Tiap modul punya 3 tier; lulus minimal
                {{ config('dojo.pass_score') }}% membuka tier berikutnya.
            </p>
        </div>
        <button type="button" class="btn btn-primary" data-dialog-open="board-create">+ Buat board</button>
    </div>

    @if ($activeAttempts->isNotEmpty())
        <section class="mb-10">
            <h2 class="mb-3 text-sm font-semibold tracking-wider text-amber-300 uppercase">Tes yang belum selesai</h2>
            <div class="card divide-y divide-zinc-800 border-amber-500/30">
                @foreach ($activeAttempts as $attempt)
                    <a href="{{ route('attempts.show', $attempt) }}" class="flex items-center justify-between gap-4 px-4 py-3 text-sm hover:bg-zinc-800/60">
                        <span>{{ $attempt->module->title }} <span class="text-zinc-500">· {{ $attempt->tier->label() }}</span></span>
                        <span class="flex items-center gap-3">
                            <span class="font-mono text-zinc-500">{{ $attempt->current_index }}/{{ $attempt->questionCount() }}</span>
                            <span class="text-emerald-400">Lanjutkan →</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($boards->isEmpty())
        <div class="card p-10 text-center">
            <p class="font-mono text-emerald-400">$ ls boards/</p>
            <p class="mt-2 text-zinc-400">Belum ada board. Buat satu untuk tiap topik, lalu impor modul ke dalamnya.</p>
            <button type="button" class="btn btn-primary mt-6" data-dialog-open="board-create">+ Buat board</button>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($boards as $board)
                @php
                    ['passed' => $passed, 'total' => $total] = $progress[$board->id];
                    $percent = $total > 0 ? (int) round($passed / $total * 100) : 0;
                @endphp
                <a href="{{ route('boards.show', $board) }}" class="card group flex flex-col p-5 transition hover:border-zinc-600">
                    <p class="font-mono text-xs text-zinc-500">~/{{ $board->slug }}</p>
                    <h2 class="mt-1 text-lg font-semibold group-hover:text-emerald-300">{{ $board->title }}</h2>
                    @if ($board->description)
                        <p class="mt-1 line-clamp-2 text-sm text-zinc-400">{{ $board->description }}</p>
                    @endif

                    <div class="mt-auto pt-5">
                        <div class="mb-1.5 flex items-center justify-between text-xs text-zinc-500">
                            <span>{{ $board->modules->count() }} modul</span>
                            <span class="font-mono">{{ $passed }}/{{ $total }} tier lulus</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-zinc-800">
                            <div class="h-full bg-emerald-500" style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($recentAttempts->isNotEmpty())
        <section class="mt-12">
            <h2 class="mb-3 text-sm font-semibold tracking-wider text-zinc-400 uppercase">Riwayat terakhir</h2>
            <div class="card divide-y divide-zinc-800">
                @foreach ($recentAttempts as $attempt)
                    <a href="{{ route('attempts.show', $attempt) }}" class="flex items-center justify-between gap-4 px-4 py-3 text-sm hover:bg-zinc-800/60">
                        <span>{{ $attempt->module->title }} <span class="text-zinc-500">· {{ $attempt->tier->label() }}</span></span>
                        <span class="flex items-center gap-3">
                            <span class="text-zinc-500">{{ $attempt->completed_at->diffForHumans() }}</span>
                            <span @class(['font-mono font-semibold', 'text-emerald-400' => $attempt->passed(), 'text-red-400' => ! $attempt->passed()])>
                                {{ (int) round($attempt->score) }}%
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <x-modal id="board-create" title="Buat board" :open="$errors->board->any()">
        <form method="POST" action="{{ route('boards.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="board-title" class="label">Nama board</label>
                <input id="board-title" name="title" type="text" maxlength="60" required value="{{ old('title') }}"
                       class="input" placeholder="mis. Linux, Docker, Kubernetes">
                @error('title', 'board')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="board-description" class="label">Deskripsi <span class="font-normal text-zinc-500">(opsional)</span></label>
                <textarea id="board-description" name="description" rows="2" maxlength="300" class="input"
                          placeholder="Apa yang dipelajari di board ini">{{ old('description') }}</textarea>
                @error('description', 'board')
                    <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn btn-ghost" data-dialog-close>Batal</button>
                <button type="submit" class="btn btn-primary">Buat board</button>
            </div>
        </form>
    </x-modal>
</x-layout>
