<?php

namespace EduLazaro\Larachat\Knowledge\Stores;

use EduLazaro\Larameili\Meili;

/**
 * A chunk of the site in Meilisearch. Vectors are computed by the app
 * (embedder `userProvided`), so Meilisearch never holds an OpenAI key. The
 * index name and the vector size come from config/larachat.php; larameili
 * adds its MEILISEARCH_PREFIX to the name.
 */
class MeiliChunk extends Meili
{
    protected static string $index = 'chunks';

    protected static array $searchable = ['title', 'heading', 'content'];

    protected static array $filterable = ['locale', 'type', 'url', 'published_at'];

    protected static array $embedders = [
        'default' => [
            'source' => 'userProvided',
            'dimensions' => 512,
        ],
    ];

    public static function configure(string $index, int $dimensions): void
    {
        static::$index = $index;
        static::$embedders['default']['dimensions'] = $dimensions;
    }
}
