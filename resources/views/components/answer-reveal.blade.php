@props(['question'])

@inject('markdown', 'App\Support\MarkdownRenderer')

@use('App\Enums\QuestionType')

<div class="space-y-4">
    @if ($question->type === QuestionType::Scenario)
        @if ($question->solution)
            <div>
                <p class="mb-1 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Contoh solusi</p>
                <div class="prose-dojo text-sm">{!! $markdown->fragment($question->solution) !!}</div>
            </div>
        @endif
    @else
        <div>
            <p class="mb-1 text-xs font-semibold tracking-wider text-zinc-400 uppercase">
                {{ count($question->acceptedAnswers()) > 1 ? 'Jawaban yang diterima' : 'Jawaban' }}
            </p>
            <div class="space-y-1">
                @foreach ($question->acceptedAnswers() as $accepted)
                    <pre class="terminal py-2">@if ($question->type === QuestionType::Command)<span class="select-none text-emerald-500">$ </span>@endif{{ $accepted }}</pre>
                @endforeach
            </div>
            @if (! empty($question->spec['patterns']) || ($question->spec['flags_any_order'] ?? false))
                <p class="mt-1 text-xs text-zinc-500">Variasi yang setara (urutan opsi, bentuk panjang, dll.) juga diterima.</p>
            @endif
        </div>
    @endif

    @if ($question->explanation)
        <div>
            <p class="mb-1 text-xs font-semibold tracking-wider text-zinc-400 uppercase">Penjelasan</p>
            <div class="prose-dojo text-sm">{!! $markdown->fragment($question->explanation) !!}</div>
        </div>
    @endif

    @if ($question->source_url)
        <p class="text-xs">
            <span class="text-zinc-500">Sumber:</span>
            <a href="{{ $question->source_url }}" target="_blank" rel="noopener noreferrer" class="break-all text-emerald-400 hover:underline">{{ $question->source_url }}</a>
        </p>
    @endif
</div>
