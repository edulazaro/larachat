<?php

namespace EduLazaro\Larachat\Tests\Fixtures;

use EduLazaro\Larachat\Support\OpenAi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

trait FakesOpenAi
{
    /** @var list<array<string, mixed>> request bodies sent to chat completions, by step */
    protected array $sent = [];

    /**
     * The scope filter, the embedding and the reply, told apart by endpoint and
     * by the scope call's 20-token limit.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $reply  the JSON the main model answers
     *         with, or one per turn in order (the last one repeats)
     */
    protected function fakeOpenAi(array $reply = [], bool $onTopic = true, array $vector = [1, 0, 0], int $status = 200): void
    {
        $defaults = ['reply' => 'Hello from the bot', 'handoff' => false, 'qualified' => false, 'visitor_name' => null, 'visitor_email' => null, 'visitor_phone' => null];
        $turns = $reply !== [] && array_is_list($reply) ? $reply : [$reply];
        $turns = array_map(fn ($turn) => $turn + $defaults, $turns);
        $next = 0;

        Http::fake(function (Request $request) use (&$next, $turns, $onTopic, $vector, $status) {
            if ($request->url() === OpenAi::EMBEDDINGS_URL) {
                $inputs = (array) $request->data()['input'];

                return Http::response(['data' => array_map(fn ($i) => ['index' => $i, 'embedding' => $vector], array_keys($inputs))]);
            }

            if ($request->url() === OpenAi::CHAT_URL) {
                $body = $request->data();
                $step = ($body['max_completion_tokens'] ?? null) === 20 ? 'scope' : 'reply';
                $this->sent[] = ['step' => $step, 'body' => $body];
                $reply = $turns[min($step === 'reply' ? $next++ : $next, count($turns) - 1)];

                return $step === 'scope'
                    ? Http::response(['choices' => [['message' => ['content' => json_encode(['on_topic' => $onTopic])]]]])
                    : Http::response(['choices' => [['message' => ['content' => json_encode($reply)], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 10]], $status);
            }

            return Http::response([], 404);
        });
    }

    protected function sentSteps(): array
    {
        return array_column($this->sent, 'step');
    }
}
