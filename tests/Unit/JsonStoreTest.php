<?php

namespace EduLazaro\Larachat\Tests\Unit;

use EduLazaro\Larachat\Knowledge\Knowledge;
use EduLazaro\Larachat\Knowledge\Stores\JsonStore;
use EduLazaro\Larachat\Tests\TestCase;

class JsonStoreTest extends TestCase
{
    protected function chunk(string $id, string $url, string $content, array $vector, string $locale = 'en', int $published = 0): array
    {
        return [
            'id' => $id, 'url' => $url, 'locale' => $locale, 'type' => 'page', 'title' => 'T ' . $id,
            'heading' => 'H ' . $id, 'content' => $content, 'published_at' => $published, 'hash' => sha1($content), 'vector' => $vector,
        ];
    }

    public function test_search_blends_vectors_and_keywords_and_filters(): void
    {
        $store = new JsonStore();
        $store->prepare();
        $store->save([
            $this->chunk('a', 'https://acme.test/legal', 'Case management for law firms', [1, 0, 0]),
            $this->chunk('b', 'https://acme.test/shop', 'Online shops and payments', [0, 1, 0]),
            $this->chunk('c', 'https://acme.test/es/legal', 'Gestión de expedientes', [1, 0, 0], 'es'),
            $this->chunk('d', 'https://acme.test/blog/soon', 'Law firms of the future', [1, 0, 0], 'en', time() + 86400),
        ]);

        $hits = (new JsonStore())->search('law firms', [0.9, 0.1, 0], 'en', 5);

        $this->assertSame(['https://acme.test/legal', 'https://acme.test/shop'], array_column($hits, 'url'), 'other locales and unpublished posts are left out');
        $this->assertSame(['url', 'title', 'heading', 'content'], array_keys($hits[0]));

        // Keywords count too: an accent-insensitive match lifts a chunk with a worse vector.
        $hits = (new JsonStore())->search('gestion expedientes', [0, 1, 0], 'es', 5);
        $this->assertSame('https://acme.test/es/legal', $hits[0]['url']);
    }

    public function test_hashes_delete_page_and_the_file_survives_a_new_instance(): void
    {
        $store = new JsonStore();
        $store->save([
            $this->chunk('a', 'https://acme.test/legal', 'One', [1, 0]),
            $this->chunk('b', 'https://acme.test/legal', 'Two', [0, 1]),
        ]);
        $store->delete(['b']);

        $fresh = new JsonStore();
        $this->assertSame(['a' => sha1('One')], $fresh->hashes());
        $this->assertCount(1, $fresh->page('https://acme.test/legal', 'en'));
        $this->assertStringStartsWith('<?php', file_get_contents($this->indexPath));

        $fresh->flush();
        $this->assertSame([], (new JsonStore())->hashes());
    }

    public function test_knowledge_keeps_at_most_two_chunks_per_page(): void
    {
        $store = new JsonStore();
        $chunks = [];
        foreach (range(1, 5) as $i) {
            $chunks[] = $this->chunk("same{$i}", 'https://acme.test/long', "Long page part {$i}", [1, 0]);
        }
        $chunks[] = $this->chunk('other', 'https://acme.test/other', 'Other page', [0.8, 0.2]);
        $store->save($chunks);
        $this->app->instance(\EduLazaro\Larachat\Contracts\KnowledgeStore::class, new JsonStore());

        $hits = app(Knowledge::class)->search('page', [1, 0], 'en');

        $this->assertCount(3, $hits);
        $this->assertSame(2, count(array_filter($hits, fn ($h) => $h['url'] === 'https://acme.test/long')));
    }
}
