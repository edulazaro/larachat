<?php

namespace EduLazaro\Larachat\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the package's own static files (panel CSS, its font, the widget
 * script) straight from vendor/, so an update of the package reaches the site
 * without publishing anything. URLs carry ?v=<mtime>, so they are cached for a
 * year and a new version is a new URL.
 */
class AssetController extends Controller
{
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

    /** The URL of one of the files, versioned by its modification time. */
    public static function url(string $file): string
    {
        return route('larachat.asset', ['file' => $file]) . '?v=' . filemtime(self::path(self::FILES[$file][0]));
    }

    protected static function path(string $relative): string
    {
        return dirname(__DIR__, 3) . '/resources/' . $relative;
    }
}
