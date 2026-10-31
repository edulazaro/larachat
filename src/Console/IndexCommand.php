<?php

namespace EduLazaro\Larachat\Console;

use EduLazaro\Larachat\Contracts\KnowledgeStore;
use EduLazaro\Larachat\Knowledge\Chunker;
use EduLazaro\Larachat\Support\OpenAi;
use Illuminate\Console\Command;

class IndexCommand extends Command
{
    protected $signature = 'larachat:index
        {--dry-run : Render and chunk the site without calling OpenAI or touching the store}
        {--fresh : Empty the store first (every chunk is embedded again)}
        {--path=* : Only index these paths (nothing stale is removed)}';

    protected $description = 'Render the public site, cut it into chunks and store them with their embeddings for the chat';

    public function handle(Chunker $chunker, KnowledgeStore $store, OpenAi $openai): int
    {
        // Rendering hundreds of pages through the kernel must not create sessions.
        config(['session.driver' => 'array']);

        $paths = $this->option('path') ?: $chunker->paths();
        $partial = (bool) $this->option('path');
        $chunks = [];
        $failed = [];

        $bar = $this->output->createProgressBar(count($paths));
        foreach ($paths as $path) {
            $pageChunks = $chunker->chunk($path);
            if ($pageChunks) {
                array_push($chunks, ...$pageChunks);
            } else {
                $failed[] = $path;
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $chars = array_sum(array_map(fn ($c) => mb_strlen($c['title'] . $c['heading'] . $c['content']), $chunks));
        $this->info(sprintf('%d pages, %d chunks, ~%d tokens.', count($paths) - count($failed), count($chunks), $chars / 4));
        if ($failed) {
            $this->warn('Not indexed (no 200 or no content): ' . implode(', ', array_slice($failed, 0, 10)) . (count($failed) > 10 ? '…' : ''));
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        if (! $openai->enabled()) {
            $this->error('No OpenAI key (larachat.openai.api_key): nothing embedded.');

            return self::FAILURE;
        }

        $store->prepare();
        if ($this->option('fresh')) {
            $store->flush();
        }

        // Only what changed is embedded again: an unchanged site costs nothing.
        $stored = $store->hashes();
        $changed = array_values(array_filter($chunks, fn ($c) => ($stored[$c['id']] ?? null) !== $c['hash']));
        $embedded = 0;
        foreach (array_chunk($changed, 100) as $batch) {
            $vectors = $openai->embed(array_map(fn ($c) => "{$c['title']}\n{$c['heading']}\n{$c['content']}", $batch));
            foreach ($batch as $i => &$chunk) {
                $chunk['vector'] = $vectors[$i];
            }
            unset($chunk);
            $store->save($batch);
            $embedded += count($batch);
        }

        $stale = $partial ? [] : array_values(array_diff(array_keys($stored), array_column($chunks, 'id')));
        $store->delete($stale);

        $this->info(sprintf('Embedded and stored %d new or changed chunks, %d unchanged, %d removed.', $embedded, count($chunks) - $embedded, count($stale)));

        return self::SUCCESS;
    }
}
