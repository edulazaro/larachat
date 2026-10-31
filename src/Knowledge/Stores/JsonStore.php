<?php

namespace EduLazaro\Larachat\Knowledge\Stores;

use EduLazaro\Larachat\Contracts\KnowledgeStore;
use Illuminate\Support\Facades\File;

/**
 * Chunks in a local file, for a site without a search server. The file is
 * PHP returning an array, so opcache keeps it compiled in shared memory and
 * loading it costs nothing per request (decoding a large JSON file on every
 * message is what this replaces). Vectors are packed float32 in base64,
 * a quarter of the size of the numbers written out as text.
 *
 * The search is hybrid like Meilisearch's: cosine similarity blended with a
 * keyword score (query words found in the title, heading or text), weighted
 * by `larachat.knowledge.semantic_ratio`.
 */
class JsonStore implements KnowledgeStore
{
    /** @var array<string, array<string, mixed>>|null */
    protected ?array $chunks = null;

    public function search(string $query, array $vector, string $locale, int $limit): array
    {
        $ratio = (float) config('larachat.knowledge.semantic_ratio', 0.6);
        $queryNorm = $this->norm($vector);
        $terms = $this->terms($query);
        $now = time();
        $scored = [];

        foreach ($this->chunks() as $chunk) {
            if ($chunk['locale'] !== $locale || $chunk['published_at'] > $now) {
                continue;
            }

            $semantic = 0.0;
            if ($queryNorm > 0 && ! empty($chunk['vector']) && $chunk['norm'] > 0) {
                $values = unpack('g*', base64_decode($chunk['vector']));
                $dot = 0.0;
                foreach ($vector as $i => $value) {
                    $dot += $value * ($values[$i + 1] ?? 0.0);
                }
                $semantic = $dot / ($queryNorm * $chunk['norm']);
            }

            $scored[] = [
                'score' => $ratio * $semantic + (1 - $ratio) * $this->keywordScore($terms, $chunk),
                'chunk' => $chunk,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($item) => $this->public($item['chunk']), array_slice($scored, 0, $limit));
    }

    public function page(string $url, string $locale, int $limit = 3): array
    {
        $found = [];
        foreach ($this->chunks() as $chunk) {
            if ($chunk['url'] === $url && $chunk['locale'] === $locale) {
                $found[] = $this->public($chunk);
                if (count($found) >= $limit) {
                    break;
                }
            }
        }

        return $found;
    }

    public function hashes(): array
    {
        return array_map(fn ($chunk) => $chunk['hash'], $this->chunks());
    }

    public function save(array $chunks): void
    {
        $all = $this->chunks();
        foreach ($chunks as $chunk) {
            $vector = array_map('floatval', $chunk['vector'] ?? []);
            unset($chunk['vector']);
            $all[$chunk['id']] = $chunk + [
                'vector' => $vector ? base64_encode(pack('g*', ...$vector)) : '',
                'norm' => $this->norm($vector),
            ];
        }
        $this->write($all);
    }

    public function delete(array $ids): void
    {
        $all = $this->chunks();
        foreach ($ids as $id) {
            unset($all[$id]);
        }
        $this->write($all);
    }

    public function prepare(): void
    {
        File::ensureDirectoryExists(dirname($this->path()));
        if (! is_file($this->path())) {
            $this->write([]);
        }
    }

    public function flush(): void
    {
        $this->write([]);
    }

    /** @return array<string, array<string, mixed>> */
    protected function chunks(): array
    {
        if ($this->chunks === null) {
            $path = $this->path();
            $data = is_file($path) ? require $path : [];
            $this->chunks = is_array($data['chunks'] ?? null) ? $data['chunks'] : [];
        }

        return $this->chunks;
    }

    /** @param  array<string, array<string, mixed>>  $chunks */
    protected function write(array $chunks): void
    {
        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));

        // Written aside and renamed, so a request never reads half a file.
        $tmp = $path . '.' . getmypid() . '.tmp';
        file_put_contents($tmp, "<?php\n\n// Built by php artisan larachat:index. Do not edit.\n\nreturn " . var_export(['version' => 1, 'chunks' => $chunks], true) . ";\n");
        rename($tmp, $path);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        $this->chunks = $chunks;
    }

    protected function path(): string
    {
        return (string) config('larachat.knowledge.json.path');
    }

    /** @param  list<float>  $vector */
    protected function norm(array $vector): float
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }

    /**
     * The query's words of three letters or more, lowercased and without
     * accents, so "inscripción" finds "inscripcion".
     *
     * @return list<string>
     */
    protected function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $this->fold($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn ($word) => mb_strlen($word) >= 3)));
    }

    /**
     * Share of the query's words found in the chunk, a word in the title or
     * heading counting double. Between 0 and 1, like the cosine.
     *
     * @param  list<string>  $terms
     * @param  array<string, mixed>  $chunk
     */
    protected function keywordScore(array $terms, array $chunk): float
    {
        if (! $terms) {
            return 0.0;
        }

        $head = $this->fold($chunk['title'] . ' ' . $chunk['heading']);
        $body = $this->fold($chunk['content']);
        $score = 0;
        foreach ($terms as $term) {
            if (str_contains($head, $term)) {
                $score += 2;
            } elseif (str_contains($body, $term)) {
                $score += 1;
            }
        }

        return $score / (2 * count($terms));
    }

    /** Lowercase without accents (a map, not iconv, whose result depends on the server's locale). */
    protected function fold(string $text): string
    {
        return strtr(mb_strtolower($text), [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'œ' => 'oe', '·' => '',
        ]);
    }

    /**
     * @param  array<string, mixed>  $chunk
     * @return array<string, mixed>
     */
    protected function public(array $chunk): array
    {
        return [
            'url' => $chunk['url'],
            'title' => $chunk['title'],
            'heading' => $chunk['heading'],
            'content' => $chunk['content'],
        ];
    }
}
