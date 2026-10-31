<?php

namespace EduLazaro\Larachat\Knowledge;

use EduLazaro\Larachat\Contracts\KnowledgeStore;
use EduLazaro\Larachat\Larachat;

/**
 * The bot's retrieval layer: which chunks go into the prompt for one message,
 * whatever store holds them.
 */
class Knowledge
{
    public function __construct(protected KnowledgeStore $store)
    {
    }

    /**
     * Hybrid search in the visitor's locale, published content only, with at
     * most two chunks per page so one long page does not fill the context.
     *
     * @param  list<float>  $vector
     * @return list<array<string, mixed>>
     */
    public function search(string $query, array $vector, string $locale): array
    {
        $limit = (int) config('larachat.knowledge.retrieved_chunks', 8);
        $perUrl = [];
        $picked = [];

        foreach ($this->store->search($query, $vector, $locale, $limit * 2) as $hit) {
            $perUrl[$hit['url']] = ($perUrl[$hit['url']] ?? 0) + 1;
            if ($perUrl[$hit['url']] <= 2) {
                $picked[] = $hit;
            }
            if (count($picked) >= $limit) {
                break;
            }
        }

        return $picked;
    }

    /**
     * The first chunks of the page the visitor is on, so "what is this?" or
     * "how much does this cost?" has the page itself in context.
     *
     * @return list<array<string, mixed>>
     */
    public function pageChunks(?string $page, string $locale): array
    {
        $path = $page ? (parse_url($page, PHP_URL_PATH) ?: '/') : null;
        if ($path === null) {
            return [];
        }

        return $this->store->page(Larachat::url($path === '/' ? '/' : rtrim($path, '/')), $locale, 3);
    }

    /**
     * The LIVE SITE CONTENT block: the passages retrieved for this message.
     *
     * @param  list<array<string, mixed>>  $chunks
     */
    public function contextBlock(array $chunks, string $locale): string
    {
        $seen = [];
        $snippets = [];
        foreach ($chunks as $chunk) {
            $key = $chunk['url'] . '|' . $chunk['heading'] . '|' . mb_substr($chunk['content'], 0, 80);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $snippets[] = '[' . (count($snippets) + 1) . '] ' . $chunk['title']
                . ($chunk['heading'] !== $chunk['title'] ? ' > ' . $chunk['heading'] : '')
                . "\nURL: {$chunk['url']}\n{$chunk['content']}";
        }

        $persona = Larachat::persona();

        return "\n\nLIVE SITE CONTENT (passages of {$persona->site()} retrieved for this message, in the visitor's language; "
            . "together with the SITE MAP above they are the only URLs you may cite; if the answer is not here say you are not sure and offer {$persona->owner()} or the closest section):\n"
            . ($snippets ? implode("\n\n", $snippets) : '(none matched)') . "\n";
    }

    /** @return list<string> the distinct URLs in a set of chunks */
    public function sources(array $chunks): array
    {
        return array_values(array_unique(array_column($chunks, 'url')));
    }
}
