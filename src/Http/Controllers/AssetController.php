<?php

namespace EduLazaro\Larachat\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the package's own static files (panel CSS, its font, the widget
 * script) straight from vendor/, so an update of the package reaches the site
 * without publishing anything. URLs carry ?v=<mtime>, so they are cached for a
 * year and a new version is a new URL.
 *
 * Where the web server answers .js and .css requests itself (nginx in front of
 * PHP often does), a file that is not on disk gets the server's own 404 and the
 * request never reaches this route. Those sites publish the files to
 * public/vendor/larachat (--tag=larachat-assets) and url() uses that copy.
 */
class AssetController extends Controller
{
    public const PUBLISHED = 'vendor/larachat';

    protected const FILES = [
        'panel.css' => ['dist/panel.css', 'text/css; charset=utf-8'],
        'widget.js' => ['js/widget.js', 'application/javascript; charset=utf-8'],
        'widget.css' => ['dist/widget.css', 'text/css; charset=utf-8'],
        'plus-jakarta-sans-latin.woff2' => ['fonts/plus-jakarta-sans-normal-latin.woff2', 'font/woff2'],
        'plus-jakarta-sans-latin-ext.woff2' => ['fonts/plus-jakarta-sans-normal-latin-ext.woff2', 'font/woff2'],
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(isset(self::FILES[$file]), 404);

        [$path, $type] = self::FILES[$file];

        return response()->file(self::path($path), [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** The URL of one of the files, versioned by its modification time: the published copy if there is one. */
    public static function url(string $file): string
    {
        $published = public_path(self::PUBLISHED . '/' . $file);
        if (is_file($published)) {
            return asset(self::PUBLISHED . '/' . $file) . '?v=' . filemtime($published);
        }

        return route('larachat.asset', ['file' => $file]) . '?v=' . filemtime(self::path(self::FILES[$file][0]));
    }

    /**
     * Each file and where its published copy goes, for vendor:publish.
     *
     * @return array<string, string>
     */
    public static function publishable(): array
    {
        $paths = [];
        foreach (self::FILES as $file => [$path]) {
            $paths[self::path($path)] = public_path(self::PUBLISHED . '/' . $file);
        }

        return $paths;
    }

    protected static function path(string $relative): string
    {
        return dirname(__DIR__, 3) . '/resources/' . $relative;
    }
}
