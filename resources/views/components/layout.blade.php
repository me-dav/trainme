@props(['title' => null, 'wide' => false])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Theme before first paint: the saved choice, otherwise the device setting. --}}
    <script>
        (() => {
            const saved = localStorage.getItem('theme');
            const system = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
            document.documentElement.dataset.theme = saved === 'light' || saved === 'dark' ? saved : system;
        })();
    </script>
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen">
    <header class="sticky top-0 z-20 border-b border-zinc-800 bg-zinc-950/90 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="flex items-center gap-2 font-mono text-sm font-semibold">
                <span class="text-accent-400 rotate-270">꩜</span>
                <span>{{ config('app.name') }}</span>
            </a>

            <div class="flex items-center gap-1">
                <button type="button" data-theme-toggle
                        class="rounded-md p-2 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200"
                        aria-label="Ganti tema terang/gelap" title="Ganti tema terang/gelap">
                    {{-- Moon in the light theme (switch to dark), sun in the dark theme (switch to light). --}}
                    <svg class="theme-icon-sun h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.06ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.06a.75.75 0 1 0-1.061 1.06l1.06 1.06Z"/></svg>
                    <svg class="theme-icon-moon h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77 7 7 0 0 0 9.958 7.967.75.75 0 0 1 1.067.853A8.5 8.5 0 1 1 6.647 1.921a.75.75 0 0 1 .808.083Z" clip-rule="evenodd"/></svg>
                </button>

                @auth
                    <nav class="flex items-center gap-1 text-sm">
                        <a href="{{ route('dashboard') }}"
                           @class(['rounded-md px-3 py-1.5 hover:bg-zinc-800', 'bg-zinc-800 text-zinc-50' => request()->routeIs('dashboard', 'boards.*')])>Board</a>
                        <form method="POST" action="{{ route('logout') }}" class="ml-2">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-1.5 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200"
                                    title="{{ auth()->user()->email }}">Keluar</button>
                        </form>
                    </nav>
                @endauth
            </div>
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
