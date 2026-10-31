<?php

namespace EduLazaro\Larachat\Tests\Feature;

use EduLazaro\Larachat\Knowledge\Stores\JsonStore;
use EduLazaro\Larachat\Support\OpenAi;
use EduLazaro\Larachat\Tests\Fixtures\User;
use EduLazaro\Larachat\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class IndexAndCommandsTest extends TestCase
{
    protected array $pages = ['/', '/es/legal'];

    protected function defineRoutes($router): void
    {
        $router->get('/sitemap.xml', fn () => response(
            '<?xml version="1.0"?><urlset>' . implode('', array_map(fn ($p) => '<url><loc>https://acme.test' . $p . '</loc></url>', $this->pages)) . '</urlset>',
            200, ['Content-Type' => 'application/xml']
        ));
        $router->get('/', fn () => '<html><head><title>Home | Acme</title></head><body><main><h1>Acme</h1><p>We build platforms.</p></main></body></html>');
        $router->get('/es/legal', fn () => '<html><head><title>Legal | Acme</title></head><body><main><h1>Legal</h1><p>Software para despachos.</p></main></body></html>');
    }

    protected function fakeEmbeddings(): void
    {
        Http::fake([OpenAi::EMBEDDINGS_URL => fn (Request $r) => Http::response([
            'data' => array_map(fn ($i) => ['index' => $i, 'embedding' => [0.1, 0.2, 0.3]], array_keys((array) $r['input'])),
        ])]);
    }

    public function test_index_embeds_only_what_changed_and_removes_what_is_gone(): void
    {
        $this->fakeEmbeddings();

        $this->artisan('larachat:index')->expectsOutputToContain('Embedded and stored 2 new or changed chunks')->assertSuccessful();
        $this->assertCount(2, (new JsonStore())->hashes());
        $this->assertSame(sha1('/es/legal#0'), array_keys((new JsonStore())->hashes())[1]);

        $this->artisan('larachat:index')->expectsOutputToContain('Embedded and stored 0 new or changed chunks, 2 unchanged')->assertSuccessful();
        Http::assertSentCount(1);

        $this->pages = ['/'];
        $this->artisan('larachat:index')->expectsOutputToContain('1 removed')->assertSuccessful();
        $this->assertCount(1, (new JsonStore())->hashes());
    }

    public function test_dry_run_calls_nothing(): void
    {
        Http::fake();

        $this->artisan('larachat:index --dry-run')->expectsOutputToContain('2 pages, 2 chunks')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertFileDoesNotExist($this->indexPath);
    }

    public function test_admin_command_creates_the_user_with_a_one_time_password(): void
    {
        $this->artisan('larachat:admin edu@acme.test')->expectsOutputToContain('Created edu@acme.test')->assertSuccessful();
        $user = User::where('email', 'edu@acme.test')->first();
        $this->assertSame('edu', $user->name);

        $this->artisan('larachat:admin other@acme.test')->expectsOutputToContain('is not in larachat.admins')->assertSuccessful();
        $this->artisan('larachat:admin not-an-email')->assertFailed();

        $old = $user->password;
        $this->artisan('larachat:admin edu@acme.test')->expectsOutputToContain('New password for')->assertSuccessful();
        $this->assertNotSame($old, $user->fresh()->password);
        $this->assertFalse(Hash::check('', $user->fresh()->password));
    }

    public function test_widget_view_config_and_assets(): void
    {
        app()->setLocale('es');
        $html = view('larachat::widget')->render();

        $this->assertStringContainsString('data-larachat-config=', $html);
        $this->assertStringContainsString('¡Hola! Soy Nodo, el asistente de Acme.', $html);
        $config = json_decode(html_entity_decode(preg_match('/data-larachat-config="([^"]+)"/', $html, $m) ? $m[1] : '{}'), true);
        $this->assertSame('/api/chat/send', $config['urls']['send']);
        $this->assertSame('Hablando con Edu', $config['texts']['talking']);
        $this->assertMatchesRegularExpression('#/larachat/assets/widget\.js\?v=\d+#', $html);

        $this->get('/larachat/assets/widget.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
        $this->get('/larachat/assets/panel.css')->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->get('/larachat/assets/..%2F..%2Fcomposer.json')->assertNotFound();
        $this->get('/larachat/assets/secret.php')->assertNotFound();
    }
}
