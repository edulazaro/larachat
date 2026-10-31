<?php

namespace EduLazaro\Larachat\Knowledge;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use EduLazaro\Larachat\Larachat;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Turns the public site into knowledge chunks. Every URL of the sitemap is
 * rendered through the HTTP kernel (no network, so it is exactly what
 * visitors see), the main content is kept and cut into sections by heading,
 * and consecutive small sections are packed into chunks of about
 * `larachat.knowledge.chunk_chars` characters.
 */
class Chunker
{
    protected const BLOCK_TAGS = [
        'div', 'section', 'article', 'aside', 'main', 'header', 'footer', 'nav', 'form', 'fieldset',
        'p', 'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'dl', 'dt', 'dd',
        'figure', 'figcaption', 'blockquote', 'details', 'summary', 'pre',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    ];

    /** Never content, on any site. */
    protected const DROP = [
        'header', 'nav', 'footer', 'script', 'style', 'svg', 'noscript', 'template', 'canvas', 'iframe',
        'select', 'option', 'input', 'textarea', 'button', 'img', 'picture', 'video',
    ];

    /** @return list<string> path of every page in the sitemap */
    public function paths(): array
    {
        $paths = [];
        foreach ($this->locs($this->sitemap((string) config('larachat.knowledge.sitemap', 'sitemap.xml'))) as $loc) {
            $path = parse_url($loc, PHP_URL_PATH) ?: '/';
            // A sitemap index lists other sitemaps: follow them, one level down.
            if (str_ends_with($path, '.xml')) {
                foreach ($this->locs($this->sitemap($path)) as $page) {
                    $paths[] = parse_url($page, PHP_URL_PATH) ?: '/';
                }
                continue;
            }
            $paths[] = $path;
        }

        return array_values(array_unique($paths));
    }

    /** @return list<array<string, mixed>> the chunks of one page, empty if it does not render */
    public function chunk(string $path): array
    {
        $html = $this->render($path);

        return $html === null ? [] : $this->extract($path, $html);
    }

    /** @return list<array<string, mixed>> */
    public function extract(string $path, string $html): array
    {
        // libxml's HTML parser ends a <script> at the first "</" inside a JS string,
        // which spills the rest of the code into the page text: strip them first.
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xp = new DOMXPath($doc);

        $site = preg_quote(Larachat::persona()->site(), '/');
        $title = trim(preg_replace('/\s*\|\s*' . $site . '\s*$/u', '', $xp->evaluate('string(//title)')));
        $published = $xp->evaluate('string(//meta[@property="article:published_time"]/@content)');
        $main = $xp->query('//' . $this->xpath((string) config('larachat.knowledge.content', 'main')))->item(0);
        if (! $main) {
            return [];
        }

        $drop = implode(' | ', array_map(fn ($tag) => './/' . $tag, self::DROP))
            . ' | .//*[@aria-hidden="true"]';
        foreach ((array) config('larachat.knowledge.drop', []) as $selector) {
            $drop .= ' | .//' . $this->xpath($selector);
        }
        foreach (iterator_to_array($xp->query($drop, $main)) as $node) {
            $node->parentNode?->removeChild($node);
        }
        foreach (iterator_to_array($xp->query('.//br', $main)) as $br) {
            $br->parentNode?->replaceChild($doc->createTextNode(' '), $br);
        }

        $sections = [['heading' => $title, 'lines' => []]];
        $this->walk($main, $sections);

        return $this->pack($path, $title, $published, $sections);
    }

    protected function render(string $path): ?string
    {
        // A locale middleware may only switch for prefixed paths, and setLocale()
        // also rewrites config('app.locale'): reset to the fallback before each page.
        app()->setLocale(config('app.fallback_locale'));

        $kernel = app(Kernel::class);
        $request = Request::create(Larachat::url($path), 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response->getStatusCode() === 200 ? (string) $response->getContent() : null;
    }

    /** The sitemap as a file in public/ when there is one, else rendered by the app. */
    protected function sitemap(string $source): string
    {
        $file = public_path(ltrim($source, '/'));
        if (is_file($file)) {
            return (string) file_get_contents($file);
        }

        return (string) $this->render('/' . ltrim($source, '/'));
    }

    /** @return list<string> */
    protected function locs(string $xml): array
    {
        preg_match_all('#<loc>(.*?)</loc>#s', $xml, $m);

        return array_map(fn ($url) => trim(html_entity_decode($url)), $m[1]);
    }

    /**
     * A small selector syntax for the config: "main" (a tag), "#id", ".class"
     * (a whole class name) and ".prefix*" (any class containing it).
     */
    protected function xpath(string $selector): string
    {
        $selector = trim($selector);

        return match (true) {
            str_starts_with($selector, '#') => '*[@id="' . substr($selector, 1) . '"]',
            str_starts_with($selector, '.') && str_ends_with($selector, '*') => '*[contains(@class, "' . substr($selector, 1, -1) . '")]',
            str_starts_with($selector, '.') => '*[contains(concat(" ", normalize-space(@class), " "), " ' . substr($selector, 1) . ' ")]',
            default => $selector,
        };
    }

    /** Collect text leaves in document order; h1-h3 open a new section. */
    protected function walk(DOMNode $node, array &$sections): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $this->addLine($sections, $child->wholeText);
                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, ['h1', 'h2', 'h3'], true)) {
                $heading = $this->clean($child->textContent);
                if ($heading !== '') {
                    $sections[] = ['heading' => $heading, 'lines' => []];
                }
                continue;
            }

            if ($this->hasBlockChild($child)) {
                $this->walk($child, $sections);
            } else {
                $this->addLine($sections, $child->textContent);
            }
        }
    }

    protected function hasBlockChild(DOMElement $element): bool
    {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if (in_array(strtolower($descendant->tagName), self::BLOCK_TAGS, true)) {
                return true;
            }
        }

        return false;
    }

    protected function addLine(array &$sections, string $text): void
    {
        $text = $this->clean($text);
        if (mb_strlen($text) < 2) {
            return;
        }
        $last = count($sections) - 1;
        if (end($sections[$last]['lines']) !== $text) {
            $sections[$last]['lines'][] = $text;
        }
    }

    protected function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Pack sections into chunks of up to the configured size. A chunk takes the
     * heading of its first section; later sections inside it keep their
     * heading as a line.
     *
     * @return list<array<string, mixed>>
     */
    protected function pack(string $path, string $title, string $published, array $sections): array
    {
        $max = $this->maxChars();
        $pieces = [];
        foreach ($sections as $section) {
            if ($section['lines'] === []) {
                continue;
            }
            foreach ($this->split(implode("\n", $section['lines'])) as $i => $text) {
                $pieces[] = ['heading' => $section['heading'], 'text' => $text, 'continued' => $i > 0];
            }
        }

        $chunks = [];
        $current = null;
        foreach ($pieces as $piece) {
            $body = $piece['text'];
            if ($current && mb_strlen($current['content']) + mb_strlen($body) + mb_strlen($piece['heading']) + 2 <= $max) {
                $current['content'] .= "\n" . ($piece['continued'] ? '' : $piece['heading'] . "\n") . $body;
                continue;
            }
            if ($current) {
                $chunks[] = $current;
            }
            $current = ['heading' => $piece['heading'], 'content' => $body];
        }
        if ($current) {
            $chunks[] = $current;
        }

        $locale = Larachat::locale($path);
        $url = Larachat::url($path);
        $publishedAt = $published !== '' ? (int) strtotime($published) : 0;

        return array_map(fn ($chunk, $i) => [
            'id' => sha1($path . '#' . $i),
            'url' => $url,
            'locale' => $locale,
            'type' => $this->type($path),
            'title' => $title,
            'heading' => $chunk['heading'],
            'content' => $chunk['content'],
            'published_at' => $publishedAt,
            'hash' => sha1($title . "\n" . $chunk['heading'] . "\n" . $chunk['content']),
        ], $chunks, array_keys($chunks));
    }

    /** @return list<string> */
    protected function split(string $text): array
    {
        $max = $this->maxChars();
        if (mb_strlen($text) <= $max) {
            return [$text];
        }

        $parts = [];
        $buffer = '';
        foreach (preg_split('/(?<=[.!?])\s+|\n/u', $text) as $sentence) {
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($sentence) + 1 > $max) {
                $parts[] = $buffer;
                $buffer = '';
            }
            $buffer = $buffer === '' ? $sentence : $buffer . ' ' . $sentence;
        }
        if ($buffer !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /** The first path segment after the locale prefix ("blog", "tools"...), or "page". */
    protected function type(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path)));
        if (($segments[0] ?? '') !== '' && in_array($segments[0], config('larachat.locales', []), true)) {
            array_shift($segments);
        }

        return $segments[0] ?? 'page';
    }

    protected function maxChars(): int
    {
        return (int) config('larachat.knowledge.chunk_chars', 1400);
    }
}
