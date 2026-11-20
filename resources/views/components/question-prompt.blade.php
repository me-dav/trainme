@props(['question'])

@inject('markdown', 'App\Support\MarkdownRenderer')

<div class="prose-dojo text-[15px]">
    {!! $markdown->fragment($question->prompt) !!}
</div>

@if ($question->context)
    <pre class="terminal mt-4 whitespace-pre">{{ $question->context }}</pre>
@endif
