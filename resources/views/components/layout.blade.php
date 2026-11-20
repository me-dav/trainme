@props(['title' => null, 'wide' => false])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <header class="sticky top-0 z-20 border-b border-zinc-800 bg-zinc-950/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="flex items-center gap-2 font-mono text-sm font-semibold">
                <span class="text-emerald-400 rotate-270">꩜</span>
                <span>{{ config('app.name') }}</span>
            </a>

            @auth
                <nav class="flex items-center gap-1 text-sm">
                    <a href="{{ route('dashboard') }}"
                       @class(['rounded-md px-3 py-1.5 hover:bg-zinc-800', 'bg-zinc-800 text-white' => request()->routeIs('dashboard', 'boards.*')])>Board</a>
                    <form method="POST" action="{{ route('logout') }}" class="ml-2">
                        @csrf
                        <button type="submit" class="rounded-md px-3 py-1.5 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200"
                                title="{{ auth()->user()->email }}">Keluar</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main @class(['mx-auto px-4 py-8', 'max-w-6xl' => $wide, 'max-w-4xl' => ! $wide])>
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200" role="alert">
                {{ session('error') }}
            </div>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
