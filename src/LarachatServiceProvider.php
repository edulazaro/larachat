<?php

namespace EduLazaro\Larachat;

use EduLazaro\Larachat\Console\AdminCommand;
use EduLazaro\Larachat\Console\IndexCommand;
use EduLazaro\Larachat\Console\TelegramCommand;
use EduLazaro\Larachat\Contracts\KnowledgeStore;
use EduLazaro\Larachat\Contracts\Persona;
use EduLazaro\Larachat\Http\Controllers\AssetController;
use EduLazaro\Larachat\Http\Controllers\ChatController;
use EduLazaro\Larachat\Http\Controllers\TelegramWebhookController;
use EduLazaro\Larachat\Http\Middleware\NoIndex;
use EduLazaro\Larachat\Http\Middleware\PanelAccess;
use EduLazaro\Larachat\Knowledge\Stores\JsonStore;
use EduLazaro\Larachat\Knowledge\Stores\MeilisearchStore;
use EduLazaro\Larachat\Livewire\Inbox;
use EduLazaro\Larachat\Livewire\Login;
use EduLazaro\Larachat\Livewire\Profile;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LarachatServiceProvider extends ServiceProvider
{
    /**
     * Register the package's bindings.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/larachat.php', 'larachat');

        $this->app->singleton(Persona::class, function ($app) {
            $class = $app['config']['larachat.persona'] ?: DefaultPersona::class;

            return $app->make($class);
        });

        $this->app->singleton(KnowledgeStore::class, function ($app) {
            return match ($app['config']['larachat.knowledge.driver']) {
                'meilisearch' => $app->make(MeilisearchStore::class),
                'json' => $app->make(JsonStore::class),
                default => $app->make($app['config']['larachat.knowledge.driver']),
            };
        });
    }

    /**
     * Boot the package: routes, views, translations, the panel's components
     * and what can be published.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'larachat');
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'larachat');

        Livewire::component('larachat.login', Login::class);
        Livewire::component('larachat.inbox', Inbox::class);
        Livewire::component('larachat.profile', Profile::class);

        $this->registerRateLimiters();
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([IndexCommand::class, AdminCommand::class, TelegramCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/larachat.php' => config_path('larachat.php'),
            ], 'larachat-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_larachat_tables.php.stub' => database_path('migrations/' . date('Y_m_d_His') . '_create_larachat_tables.php'),
            ], 'larachat-migrations');

            $this->publishes([
                __DIR__ . '/../lang' => $this->app->langPath('vendor/larachat'),
            ], 'larachat-lang');

            $this->publishes([
                __DIR__ . '/../resources/views/widget.blade.php' => resource_path('views/vendor/larachat/widget.blade.php'),
            ], 'larachat-widget');

            $this->publishes(AssetController::publishable(), 'larachat-assets');
        }
    }

    protected function registerRateLimiters(): void
    {
        $limits = [
            'larachat-init' => 'init_per_minute',
            'larachat-send' => 'send_per_minute',
            'larachat-poll' => 'poll_per_minute',
            'larachat-switch' => 'switch_per_minute',
        ];

        foreach ($limits as $name => $key) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute((int) config('larachat.limits.' . $key))->by($request->ip()));
        }
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $routes = config('larachat.routes');
        $panel = trim($routes['panel'], '/');

        // The widget's API: JSON, no session, no CSRF.
        Route::prefix(trim($routes['api'], '/'))->middleware([SubstituteBindings::class])->group(function () {
            Route::post('init', [ChatController::class, 'init'])->middleware('throttle:larachat-init')->name('larachat.init');
            Route::post('send', [ChatController::class, 'send'])->middleware('throttle:larachat-send')->name('larachat.send');
            Route::post('contact', [ChatController::class, 'contact'])->middleware('throttle:larachat-switch')->name('larachat.contact');
            Route::post('contact-form', [ChatController::class, 'contactForm'])->middleware('throttle:larachat-switch')->name('larachat.contact_form');
            Route::get('poll', [ChatController::class, 'poll'])->middleware('throttle:larachat-poll')->name('larachat.poll');
            Route::post('switch-ai', [ChatController::class, 'switchToAi'])->middleware('throttle:larachat-switch')->name('larachat.switch');
        });

        Route::post(trim($routes['telegram_webhook'], '/'), TelegramWebhookController::class)
            ->middleware('throttle:60,1')
            ->name('larachat.telegram');

        Route::get(trim($routes['assets'], '/') . '/{file}', AssetController::class)
            ->where('file', '[A-Za-z0-9._-]+')
            ->name('larachat.asset');

        $login = trim($routes['login'], '/');

        Route::middleware(['web', NoIndex::class])->group(function () use ($panel, $login) {
            if ($login === $panel) {
                // One address: a guest finds the login there, an admin the inbox.
                Route::get($panel, fn () => app(Larachat::isAdmin(Auth::user()) ? Inbox::class : Login::class)())
                    ->middleware('throttle:20,1')
                    ->name('larachat.panel');
            } else {
                Route::get($login, Login::class)->middleware('throttle:20,1')->name('larachat.login');
                Route::get($panel, Inbox::class)->middleware(PanelAccess::class)->name('larachat.panel');
            }

            Route::middleware(PanelAccess::class)->group(function () use ($panel) {
                Route::get($panel . '/profile', Profile::class)->name('larachat.profile');
                Route::post($panel . '/logout', function (Request $request) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->to(Larachat::loginUrl());
                })->name('larachat.logout');
            });
        });
    }
}
