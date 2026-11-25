<?php

namespace App\Services\Ai;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

/**
 * Minimal client for an OpenAI-compatible `/chat/completions` endpoint.
 *
 * Requests are streamed: writing a whole module takes minutes, and without streaming the
 * server sends nothing until it is done, so a long answer looks exactly like a dead connection.
 * Streaming lets the client give up only when no data arrives for `idle_timeout` seconds.
 */
class ChatClient
{
    private const PROGRESS_INTERVAL_SECONDS = 3;

    public function isConfigured(): bool
    {
        return filled(config('services.llm.base_url')) && filled(config('services.llm.key'));
    }

    /**
     * One streamed request; `$onProgress` gets the characters written so far every few seconds.
     *
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  array<string, mixed>  $options  extra request fields, e.g. `['thinking' => ['type' => 'disabled']]`
     * @param  Closure(int): void|null  $onProgress
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, finish_reason: string|null}
     *
     * @throws RuntimeException when the endpoint is unreachable, stalls, or answers with an error.
     */
    public function complete(array $messages, array $options = [], ?Closure $onProgress = null): array
    {
        $this->ensureConfigured();
        $idleTimeout = (int) config('services.llm.idle_timeout');

        try {
            $response = Http::withToken((string) config('services.llm.key'))
                ->withOptions(['stream' => true, 'read_timeout' => $idleTimeout])
                ->connectTimeout(30)
                ->timeout($idleTimeout)
                ->post($this->endpoint(), $this->payload($messages, $options));
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Tidak bisa menghubungi layanan AI: '.$exception->getMessage(), previous: $exception);
        }

        return $this->result($response, $onProgress);
    }

    /**
     * Several requests at the same time. The server still streams (so the connection never sits
     * silent), but each answer is read once it is complete; curl gives up on a request that receives
     * nothing for `idle_timeout` seconds. A failed request does not stop the others.
     *
     * @param  array<string, list<array{role: string, content: string|list<array<string, mixed>>}>>  $conversations  keyed by name
     * @param  array<string, mixed>  $options  extra request fields for every request
     * @param  Closure(string): void|null  $onFinished  called with the name of each request as it completes
     * @return array<string, array{content: string, prompt_tokens: int, completion_tokens: int, finish_reason: string|null}|RuntimeException>
     */
    public function completeMany(array $conversations, array $options = [], ?Closure $onFinished = null): array
    {
        $this->ensureConfigured();
        $idleTimeout = (int) config('services.llm.idle_timeout');

        $responses = Http::pool(fn (Pool $pool): array => collect($conversations)
            ->map(fn (array $messages, string $name) => $pool->as($name)
                ->withToken((string) config('services.llm.key'))
                ->withOptions([
                    'curl' => [CURLOPT_LOW_SPEED_LIMIT => 1, CURLOPT_LOW_SPEED_TIME => $idleTimeout],
                    'on_stats' => function () use ($onFinished, $name): void {
                        if ($onFinished !== null) {
                            $onFinished($name);
                        }
                    },
                ])
                ->connectTimeout(30)
                ->timeout((int) config('services.llm.timeout'))
                ->post($this->endpoint(), $this->payload($messages, $options)))
            ->all());

        $results = [];

        foreach (array_keys($conversations) as $name) {
            $response = $responses[$name] ?? null;

            try {
                if (! $response instanceof Response) {
                    $reason = $response instanceof Throwable ? $response->getMessage() : 'tidak ada jawaban';

                    throw new RuntimeException('Tidak bisa menghubungi layanan AI: '.$reason);
                }

                $results[$name] = $this->result($response, null);
            } catch (RuntimeException $exception) {
                $results[$name] = $exception;
            }
        }

        return $results;
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('AI belum dikonfigurasi: isi LLM_BASE_URL dan LLM_API_KEY di .env.');
        }
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.llm.base_url'), '/').'/chat/completions';
    }

    /**
     * @param  list<array{role: string, content: string|list<array<string, mixed>>}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function payload(array $messages, array $options = []): array
    {
        return [
            'model' => config('services.llm.model'),
            'messages' => $messages,
            'max_tokens' => (int) config('services.llm.max_output_tokens'),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            ...$options,
        ];
    }

    /**
     * @param  Closure(int): void|null  $onProgress
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, finish_reason: string|null}
     */
    private function result(Response $response, ?Closure $onProgress): array
    {
        if ($response->failed()) {
            $message = $response->json('error.message') ?? $response->body();

            throw new RuntimeException('Layanan AI menolak permintaan (HTTP '.$response->status().'): '.mb_strimwidth((string) $message, 0, 500, '…'));
        }

        $body = $response->toPsrResponse()->getBody();
        $result = str_contains($response->header('Content-Type'), 'text/event-stream') || $this->peekIsEventStream($body)
            ? $this->readEventStream($body, (int) config('services.llm.idle_timeout'), $onProgress)
            : $this->readJson((string) $body);

        if (trim($result['content']) === '') {
            throw new RuntimeException('Layanan AI tidak mengembalikan jawaban.');
        }

        return $result;
    }

    private function peekIsEventStream(StreamInterface $body): bool
    {
        if (! $body->isSeekable()) {
            return false;
        }

        $start = $body->read(5);
        $body->rewind();

        return $start === 'data:';
    }

    /**
     * Server-sent events: one `data: {json}` line per chunk, ending with `data: [DONE]`.
     *
     * @param  Closure(int): void|null  $onProgress
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, finish_reason: string|null}
     */
    private function readEventStream(StreamInterface $body, int $idleTimeout, ?Closure $onProgress): array
    {
        $deadline = microtime(true) + (int) config('services.llm.timeout');
        $lastData = microtime(true);
        $lastProgress = 0.0;
        $buffer = '';
        $result = ['content' => '', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'finish_reason' => null];

        while (true) {
            $chunk = $body->eof() ? '' : $body->read(8192);
            $now = microtime(true);

            if ($chunk === '') {
                if ($body->eof()) {
                    break;
                }

                if ($now - $lastData > $idleTimeout) {
                    throw new RuntimeException("Layanan AI berhenti mengirim data selama {$idleTimeout} detik. Coba lagi beberapa saat lagi.");
                }

                usleep(50_000);

                continue;
            }

            if ($now > $deadline) {
                throw new RuntimeException('Jawaban AI melebihi batas waktu '.(int) config('services.llm.timeout').' detik. Coba dokumen yang lebih pendek (misalnya satu bab).');
            }

            $lastData = $now;
            $buffer .= $chunk;

            while (($newline = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newline));
                $buffer = substr($buffer, $newline + 1);

                if ($line === 'data: [DONE]') {
                    break 2;
                }

                if (str_starts_with($line, 'data:')) {
                    $this->applyChunk($result, json_decode(trim(substr($line, 5)), true));
                }
            }

            if ($onProgress !== null && $now - $lastProgress >= self::PROGRESS_INTERVAL_SECONDS) {
                $lastProgress = $now;
                $onProgress(mb_strlen($result['content']));
            }
        }

        if ($onProgress !== null) {
            $onProgress(mb_strlen($result['content']));
        }

        return $result;
    }

    /**
     * @param  array{content: string, prompt_tokens: int, completion_tokens: int, finish_reason: string|null}  $result
     */
    private function applyChunk(array &$result, mixed $chunk): void
    {
        if (! is_array($chunk)) {
            return;
        }

        if (isset($chunk['error'])) {
            throw new RuntimeException('Layanan AI mengirim error: '.mb_strimwidth((string) ($chunk['error']['message'] ?? json_encode($chunk['error'])), 0, 500, '…'));
        }

        $result['content'] .= (string) ($chunk['choices'][0]['delta']['content'] ?? '');
        $result['finish_reason'] = $chunk['choices'][0]['finish_reason'] ?? $result['finish_reason'];

        if (isset($chunk['usage'])) {
            $result['prompt_tokens'] = (int) ($chunk['usage']['prompt_tokens'] ?? 0);
            $result['completion_tokens'] = (int) ($chunk['usage']['completion_tokens'] ?? 0);
        }
    }

    /**
     * Some gateways ignore `stream` and answer with one JSON document.
     *
     * @return array{content: string, prompt_tokens: int, completion_tokens: int, finish_reason: string|null}
     */
    private function readJson(string $body): array
    {
        $json = json_decode($body, true);

        return [
            'content' => (string) ($json['choices'][0]['message']['content'] ?? ''),
            'prompt_tokens' => (int) ($json['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($json['usage']['completion_tokens'] ?? 0),
            'finish_reason' => $json['choices'][0]['finish_reason'] ?? null,
        ];
    }
}
