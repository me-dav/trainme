<x-layout title="Masuk">
    <div class="mx-auto mt-12 max-w-sm">
        <div class="mb-8 text-center">
            <p class="font-mono text-sm text-emerald-400"><span class="inline-block rotate-270">꩜</span> ssh trainme@bydav</p>
            <h1 class="mt-2 text-2xl font-semibold">Masuk ke trainme</h1>
            <p class="mt-1 text-sm text-zinc-400">Your topics, your modules, your way to learn. Belajar dengan praktik, bukan sekadar teori.</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="card space-y-4 p-6">
            @csrf

            <div>
                <label for="email" class="mb-1 block text-sm text-zinc-400">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="input">
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm text-zinc-400">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
            </div>

            <label class="flex items-center gap-2 text-sm text-zinc-400">
                <input type="checkbox" name="remember" value="1" class="rounded border-zinc-700 bg-zinc-950 text-emerald-500">
                Ingat saya
            </label>

            @error('email')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            <button type="submit" class="btn btn-primary w-full">Masuk</button>
        </form>

        <p class="mt-4 text-center text-xs text-zinc-500">Belum punya akun? Buat lewat <code class="font-mono">php artisan dojo:user email@kamu</code>.</p>
    </div>
</x-layout>
