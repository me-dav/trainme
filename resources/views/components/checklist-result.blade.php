@props(['feedback'])

@if (! empty($feedback['checklist']))
    <div>
        <p class="mb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase">
            Checklist — {{ (int) round(($feedback['ratio'] ?? 0) * 100) }}% terpenuhi (minimal {{ (int) round(($feedback['pass_ratio'] ?? 0) * 100) }}%)
        </p>
        <ul class="space-y-1.5 text-sm">
            @foreach ($feedback['checklist'] as $item)
                @php
                    $good = $item['forbid'] ? ! $item['hit'] : $item['hit'];
                @endphp
                <li class="flex items-start gap-2">
                    <span @class(['mt-0.5 font-mono text-xs', 'text-emerald-400' => $good, 'text-red-400' => ! $good])>{{ $good ? '[✓]' : '[✗]' }}</span>
                    <span @class(['text-zinc-300' => $good, 'text-zinc-400' => ! $good])>
                        {{ $item['label'] }}
                        @if ($item['required'])
                            <span class="badge ml-1 bg-zinc-800 text-zinc-400">wajib</span>
                        @endif
                        @if ($item['forbid'] && $item['hit'])
                            <span class="badge ml-1 bg-red-500/15 text-red-300">terdeteksi — anti-pattern</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
