<?php

namespace EduLazaro\Larachat\Tests\Unit;

use EduLazaro\Larachat\Knowledge\Chunker;
use EduLazaro\Larachat\Tests\TestCase;

class ChunkerTest extends TestCase
{
    protected function html(string $main): string
    {
        return '<html><head><title>Legal platforms | Acme</title></head><body>'
            . '<header>Menu that must not count</header>'
            . '<main>' . $main . '</main><footer>Footer text</footer>'
            . '<script>var s = "</div> not text";</script></body></html>';
    }

    public function test_sections_by_heading_with_the_site_name_out_of_the_title(): void
    {
        config(['larachat.knowledge.drop' => ['.chat-widget', '.promo*']]);

        $chunks = (new Chunker())->extract('/es/legal', $this->html(
            '<h1>Plataformas legales</h1><p>Construimos software para despachos.</p>'
            . '<div class="chat-widget">Chat noise</div><div class="promo-banner">Promo</div>'
            . '<h2>Gestión de expedientes</h2><ul><li>Plazos</li><li>Documentos</li></ul>'
        ));

        $this->assertCount(1, $chunks, 'small sections are packed together');
        $chunk = $chunks[0];
        $this->assertSame('Legal platforms', $chunk['title']);
        $this->assertSame('es', $chunk['locale']);
        $this->assertSame('legal', $chunk['type']);
        $this->assertSame('https://acme.test/es/legal', $chunk['url']);
        $this->assertSame(sha1('/es/legal#0'), $chunk['id'], 'ids must match the ones Kenodo already stored');
        $this->assertStringContainsString('Construimos software para despachos.', $chunk['content']);
        $this->assertStringContainsString("Gestión de expedientes\nPlazos", $chunk['content']);
        $this->assertStringNotContainsString('Chat noise', $chunk['content']);
        $this->assertStringNotContainsString('Promo', $chunk['content']);
        $this->assertStringNotContainsString('Menu', $chunk['content']);
        $this->assertStringNotContainsString('not text', $chunk['content']);
        $this->assertSame(sha1("Legal platforms\n{$chunk['heading']}\n{$chunk['content']}"), $chunk['hash']);
    }

    public function test_long_pages_are_split_near_the_size_limit(): void
    {
        config(['larachat.knowledge.chunk_chars' => 300]);
        $paragraphs = implode('', array_map(fn ($i) => "<h2>Section {$i}</h2><p>" . str_repeat("Sentence number {$i}. ", 12) . '</p>', range(1, 5)));

        $chunks = (new Chunker())->extract('/', $this->html($paragraphs));

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(320, mb_strlen($chunk['content']));
            $this->assertSame('en', $chunk['locale']);
            $this->assertSame('https://acme.test', $chunk['url']);
        }
    }

    public function test_a_page_without_the_content_element_gives_nothing(): void
    {
        $this->assertSame([], (new Chunker())->extract('/x', '<html><body><div>No main here</div></body></html>'));
    }
}
