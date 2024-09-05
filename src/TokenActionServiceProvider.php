<?php

namespace Laragear\TokenAction;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\BindingRegistrar;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laragear\Meta\BootHelpers;

class TokenActionServiceProvider extends ServiceProvider
{
    use BootHelpers;

    public const CONFIG = __DIR__ . '/../config/token-action.php';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(static::CONFIG, 'token-action');

        $this->app->singleton(Store::class, static function (Application $app): Store {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');

            return new Store(
                $app->make('cache'),
                $config->get('token-action.default'),
                $config->get('token-action.prefix'),
            );
        });

        $this->app->bind(Builder::class, static function (Application $app): Builder {
            return new Builder($app->make(Store::class));
        });

        Token::$generator = Str::ulid(...); // @phpstan-ignore-line
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(Repository $config): void
    {
        $this->withMiddleware(Http\Middleware\TokenValidateMiddleware::class)
            ->as($config->get('token-action.middleware.validate'));
        $this->withMiddleware(Http\Middleware\TokenConsumeMiddleware::class)
            ->as($config->get('token-action.middleware.consume'));

        // If the binding is truthy, register it as a routing binding key.
        if ($binding = $config->get('token-action.binding')) {
            $this->app->make(BindingRegistrar::class)->bind($binding, function (string $value): Token {
                return $this->app->make(Builder::class)->findOrFail($value);
            });
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([static::CONFIG => $this->app->configPath('token-action.php')], 'config');
        }
    }
}
