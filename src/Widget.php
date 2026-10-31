<?php

namespace EduLazaro\Larachat;

use EduLazaro\Larachat\Http\Controllers\AssetController;

/**
 * What any widget markup needs from the package: its configuration (the API
 * URLs, the texts in the page's language, who the bot and the owner are) and
 * the script that runs it. A site with its own markup writes
 *
 *     <div data-larachat data-larachat-config="{{ \EduLazaro\Larachat\Widget::config() }}"> ... </div>
 *     {!! \EduLazaro\Larachat\Widget::script() !!}
 */
class Widget
{
    /**
     * The widget's configuration as JSON, for the data-larachat-config
     * attribute (Blade's {{ }} escapes it for the attribute).
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function config(array $overrides = []): string
    {
        $persona = Larachat::persona();
        $locale = app()->getLocale();
        $replace = ['bot' => $persona->name(), 'owner' => $persona->owner(), 'site' => $persona->site()];

        $texts = [];
        foreach ((array) __('larachat::widget', [], $locale) as $key => $text) {
            $texts[$key] = __('larachat::widget.' . $key, $replace, $locale);
        }

        return json_encode(array_replace_recursive([
            'urls' => [
                'init' => route('larachat.init', [], false),
                'send' => route('larachat.send', [], false),
                'contact' => route('larachat.contact', [], false),
                'poll' => route('larachat.poll', [], false),
                'switch' => route('larachat.switch', [], false),
            ],
            'texts' => $texts,
            'bot' => $persona->name(),
            'owner' => $persona->owner(),
            'site' => $persona->site(),
            'storageKey' => config('larachat.widget.storage_key', 'larachat_token'),
            'lockScroll' => (bool) config('larachat.widget.lock_scroll', false),
            'nudgeSeconds' => (int) config('larachat.widget.nudge_seconds', 5),
            'waitSeconds' => (int) config('larachat.limits.human_wait_seconds', 10),
        ], $overrides), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** One text of the widget in the page's language, with the names filled in. */
    public static function text(string $key): string
    {
        $persona = Larachat::persona();

        return (string) __('larachat::widget.' . $key, [
            'bot' => $persona->name(), 'owner' => $persona->owner(), 'site' => $persona->site(),
        ]);
    }

    /**
     * The script tag. data-navigate-once: with wire:navigate it runs once and
     * sets up the widget again after each page swap by itself.
     */
    public static function script(): string
    {
        return '<script src="' . e(AssetController::url('widget.js')) . '" defer data-navigate-once></script>';
    }

    /** The default widget's stylesheet. */
    public static function styles(): string
    {
        return '<link rel="stylesheet" href="' . e(AssetController::url('widget.css')) . '" data-navigate-once>';
    }
}
