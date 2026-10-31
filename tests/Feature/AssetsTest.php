<?php

namespace EduLazaro\Larachat\Tests\Feature;

use EduLazaro\Larachat\Http\Controllers\AssetController;
use EduLazaro\Larachat\Tests\TestCase;
use Illuminate\Support\Facades\File;

class AssetsTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(public_path(AssetController::PUBLISHED));

        parent::tearDown();
    }

    public function test_assets_come_from_the_route_until_they_are_published(): void
    {
        $this->assertStringContainsString('/larachat/assets/widget.js?v=', AssetController::url('widget.js'));
        $this->get('/larachat/assets/widget.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

        $this->artisan('vendor:publish', ['--tag' => 'larachat-assets', '--force' => true])->assertSuccessful();

        foreach (['widget.js', 'widget.css', 'panel.css', 'plus-jakarta-sans-latin.woff2', 'plus-jakarta-sans-latin-ext.woff2'] as $file) {
            $this->assertFileExists(public_path('vendor/larachat/' . $file));
        }
        $this->assertStringContainsString('/vendor/larachat/widget.js?v=', AssetController::url('widget.js'));
        $this->assertStringContainsString('/vendor/larachat/panel.css?v=', AssetController::url('panel.css'));
    }
}
