<?php

namespace EduLazaro\Larachat\Contracts;

/**
 * Where the site's chunks live and how they are searched. A chunk is an array
 * with id, url, locale, type, title, heading, content, published_at and hash;
 * the indexer adds `vector` (the embedding) when it saves one.
 */
interface KnowledgeStore
{
    /**
     * Hybrid search (keywords plus vectors) among the published chunks of one
     * locale, best first.
     *
     * @param  list<float>  $vector
     * @return list<array<string, mixed>> url, title, heading, content
     */
    public function search(string $query, array $vector, string $locale, int $limit): array;

    /**
     * The first chunks of one page.
     *
     * @return list<array<string, mixed>> url, title, heading, content
     */
    public function page(string $url, string $locale, int $limit = 3): array;

    /** @return array<string, string> id => content hash of every stored chunk */
    public function hashes(): array;

    /** @param  list<array<string, mixed>>  $chunks  with `vector` */
    public function save(array $chunks): void;

    /** @param  list<string>  $ids */
    public function delete(array $ids): void;

    /** Creates the store or brings its settings up to date. */
    public function prepare(): void;

    /** Removes every chunk. */
    public function flush(): void;
}
