<?php

namespace EduLazaro\Larachat\Tests\Feature;

use EduLazaro\Larachat\Tests\TestCase;

class WidgetTest extends TestCase
{
    public function test_the_widget_takes_the_sites_colours_and_rounding(): void
    {
        config(['larachat.widget.colors' => ['primary' => '#2563eb', 'primary_dark' => 'red;}body{display:none'], 'larachat.widget.radius' => 12]);

        $html = view('larachat::widget')->render();

        $this->assertStringContainsString('<style>.lc-w{--lc-w-primary:#2563eb;--lc-w-radius:12px}</style>', $html);
        $this->assertStringNotContainsString('display:none', $html, 'a value that is not a colour never reaches the style element');
        $this->assertStringContainsString('class="lc-w-launcher" data-lc="launcher"', $html);
        $this->assertStringContainsString('data-lc="particles"', $html);
        $this->assertStringContainsString('<textarea rows="1"', $html, 'the box takes several lines');
    }

    public function test_a_site_can_use_a_launcher_of_its_own(): void
    {
        view()->addLocation(__DIR__ . '/../Fixtures/views');
        config(['larachat.widget.launcher' => 'own-launcher', 'larachat.widget.owner_avatar' => '/img/edu.webp']);

        $html = view('larachat::widget')->render();

        $this->assertStringContainsString('<div class="lc-w-launcher-own" data-lc="launcher-wrap"><button type="button" class="my-orb" data-lc="launcher">', $html);
        $this->assertStringNotContainsString('class="lc-w-launcher"', $html);
        $this->assertStringContainsString('<img src="/img/edu.webp" alt="">', $html);
    }
}
