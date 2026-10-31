<?php

namespace EduLazaro\Larachat\Knowledge\Stores;

use EduLazaro\Larachat\Contracts\KnowledgeStore;
use Meilisearch\Contracts\DocumentsQuery;

/**
 * Chunks in Meilisearch through edulazaro/larameili, with Meilisearch's own
 * hybrid search (keywords plus the vectors the app computed).
 */
class MeilisearchStore implements KnowledgeStore
{
    public function __construct()
    {
        if (! class_exists(\EduLazaro\Larameili\Meili::class)) {
            throw new \RuntimeException(
                "larachat.knowledge.driver is 'meilisearch' but edulazaro/larameili is not installed. "
                . "Either `composer require edulazaro/larameili` or use the 'json' driver."
            );
        }

        MeiliChunk::configure(
            (string) config('larachat.knowledge.meilisearch.index', 'chunks'),
            (int) config('larachat.openai.embedding_dimensions', 512),
        );
    }

    public function search(string $query, array $vector, string $locale, int $limit): array
    {
        return MeiliChunk::client()->index(MeiliChunk::indexName())->search($query, [
            'vector' => $vector,
            'hybrid' => ['embedder' => 'default', 'semanticRatio' => (float) config('larachat.knowledge.semantic_ratio', 0.6)],
            'filter' => sprintf('locale = "%s" AND published_at <= %d', $locale, time()),
            'limit' => $limit,
            'attributesToRetrieve' => ['url', 'title', 'heading', 'content'],
        ])->getHits();
    }

    public function page(string $url, string $locale, int $limit = 3): array
    {
        return MeiliChunk::client()->index(MeiliChunk::indexName())->search('', [
            'filter' => sprintf('url = "%s" AND locale = "%s"', addcslashes($url, '"\\'), $locale),
            'limit' => $limit,
            'attributesToRetrieve' => ['url', 'title', 'heading', 'content'],
        ])->getHits();
    }

    public function hashes(): array
    {
        $hashes = [];
        $offset = 0;
        do {
            $page = MeiliChunk::index()->getDocuments(
                (new DocumentsQuery())->setFields(['id', 'hash'])->setLimit(1000)->setOffset($offset)
            );
            foreach ($page->getResults() as $doc) {
                $hashes[$doc['id']] = $doc['hash'] ?? '';
            }
            $offset += 1000;
        } while ($offset < $page->getTotal());

        return $hashes;
    }

    public function save(array $chunks): void
    {
        $documents = array_map(function (array $chunk) {
            $chunk['_vectors'] = ['default' => $chunk['vector'] ?? []];
            unset($chunk['vector']);

            return $chunk;
        }, $chunks);

        MeiliChunk::import($documents, 100);
    }

    public function delete(array $ids): void
    {
        if ($ids) {
            MeiliChunk::waitForTask(MeiliChunk::destroy($ids), 60000);
        }
    }

    public function prepare(): void
    {
        MeiliChunk::sync();
    }

    public function flush(): void
    {
        MeiliChunk::waitForTask(MeiliChunk::truncate(), 60000);
    }
}
