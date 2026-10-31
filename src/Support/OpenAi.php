<?php

namespace EduLazaro\Larachat\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The two OpenAI endpoints the chat uses, over plain HTTP: chat completions
 * and embeddings. Every call logs its token usage as "Larachat usage".
 */
class OpenAi
{
    public const CHAT_URL = 'https://api.openai.com/v1/chat/completions';

    public const EMBEDDINGS_URL = 'https://api.openai.com/v1/embeddings';

    public function key(): ?string
    {
        return config('larachat.openai.api_key') ?: null;
    }

    public function enabled(): bool
    {
        return $this->key() !== null;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $options  model, max_tokens, reasoning_effort, response_format, timeout, step
     * @return array<string, mixed>
     */
    public function chat(array $messages, array $options = []): array
    {
        $payload = array_filter([
            'model' => $options['model'] ?? config('larachat.openai.model'),
            'messages' => $messages,
            'max_completion_tokens' => $options['max_tokens'] ?? config('larachat.openai.max_tokens'),
            'reasoning_effort' => $options['reasoning_effort'] ?? config('larachat.openai.reasoning_effort'),
            'response_format' => $options['response_format'] ?? null,
            // GPT-6 caching: in the default implicit mode every request writes the whole
            // prompt at 1.25x and a prompt with new passages never matches it again.
            // Explicit mode only caches what carries a prompt_cache_breakpoint.
            'prompt_cache_options' => ['mode' => 'explicit'],
        ], fn ($value) => $value !== null);

        $response = Http::withToken((string) $this->key())
            ->timeout($options['timeout'] ?? 60)
            ->post(self::CHAT_URL, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI API error: ' . $response->status() . ' ' . $response->body());
        }

        $this->logUsage($options['step'] ?? 'chat', $payload['model'], $response->json('usage'));
        if ($response->json('choices.0.finish_reason') === 'length') {
            Log::warning('Larachat: reply cut by max_completion_tokens', ['model' => $payload['model']]);
        }

        return $response->json();
    }

    /**
     * Request body for the embeddings endpoint (also sent inside Http::pool).
     *
     * @return array<string, mixed>
     */
    public function embeddingPayload(string|array $input): array
    {
        return [
            'model' => config('larachat.openai.embedding_model'),
            'input' => $input,
            'dimensions' => (int) config('larachat.openai.embedding_dimensions'),
        ];
    }

    /**
     * @param  list<string>  $texts
     * @return list<list<float>> one vector per text, in the same order
     */
    public function embed(array $texts): array
    {
        $response = Http::withToken((string) $this->key())
            ->timeout(120)
            ->retry(2, 2000)
            ->post(self::EMBEDDINGS_URL, $this->embeddingPayload(array_values($texts)));

        if (! $response->successful()) {
            throw new \RuntimeException('OpenAI embeddings error: ' . $response->status() . ' ' . $response->body());
        }

        return collect($response->json('data'))->sortBy('index')->pluck('embedding')->values()->all();
    }

    public function logUsage(string $step, ?string $model, ?array $usage): void
    {
        if (! $usage) {
            return;
        }

        Log::info('Larachat usage', [
            'step' => $step,
            'model' => $model,
            'prompt' => $usage['prompt_tokens'] ?? null,
            'cached' => $usage['prompt_tokens_details']['cached_tokens'] ?? null,
            'cache_write' => $usage['prompt_tokens_details']['cache_write_tokens'] ?? null,
            'completion' => $usage['completion_tokens'] ?? null,
            'reasoning' => $usage['completion_tokens_details']['reasoning_tokens'] ?? null,
        ]);
    }
}
