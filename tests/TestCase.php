<?php

namespace EduLazaro\Larachat\Tests;

use EduLazaro\Larachat\LarachatServiceProvider;
use EduLazaro\Larachat\Tests\Fixtures\TestPersona;
use EduLazaro\Larachat\Tests\Fixtures\User;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected string $indexPath;

    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class, LarachatServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->indexPath = sys_get_temp_dir() . '/larachat-test-' . getmypid() . '-' . uniqid() . '.php';

        $config = $app['config'];
        $config->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $config->set('cache.default', 'array');
        $config->set('database.default', 'testing');
        $config->set('auth.providers.users.model', User::class);
        $config->set('larachat.persona', TestPersona::class);
        $config->set('larachat.locales', ['en', 'es']);
        $config->set('larachat.default_locale', 'en');
        $config->set('larachat.admins', ['edu@acme.test']);
        $config->set('larachat.openai.api_key', 'sk-test');
        $config->set('larachat.knowledge.driver', 'json');
        $config->set('larachat.knowledge.json.path', $this->indexPath);
        $config->set('larachat.knowledge.base_url', 'https://acme.test');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadLaravelMigrations();
        (require __DIR__ . '/../database/migrations/create_larachat_tables.php.stub')->up();
    }

    protected function tearDown(): void
    {
        if (isset($this->indexPath) && is_file($this->indexPath)) {
            unlink($this->indexPath);
        }

        parent::tearDown();
    }

    protected function admin(array $attributes = []): User
    {
        return User::forceCreate($attributes + [
            'name' => 'Edu',
            'email' => 'edu@acme.test',
            'password' => bcrypt('secret-password'),
        ]);
    }
}
