<?php

namespace Tests;

use Illuminate\Contracts\Routing\BindingRegistrar;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laragear\MetaTesting\InteractsWithServiceProvider;
use Laragear\TokenAction\Builder;
use Laragear\TokenAction\Http\Middleware\TokenConsumeMiddleware;
use Laragear\TokenAction\Http\Middleware\TokenValidateMiddleware;
use Laragear\TokenAction\Store;
use Laragear\TokenAction\Token;
use Laragear\TokenAction\TokenActionServiceProvider;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use function method_exists;
use function now;

class ServiceProviderTest extends TestCase
{
    use InteractsWithServiceProvider;

    public function test_merges_config(): void
    {
        $this->assertConfigMerged(TokenActionServiceProvider::CONFIG);
    }

    public function test_publishes_config(): void
    {
        $this->assertPublishes($this->app->configPath('token-action.php'), 'config');
    }

    protected function stopTime(): void
    {
        $this->freezeSecond();
    }

    public function test_registers_container_services(): void
    {
        $this->assertHasSingletons(Store::class);
        $this->assertHasServices(Builder::class);
    }

    public function test_sets_ulid_as_default_token_generator(): void
    {
        static::assertTrue(Str::isUlid((string)(Token::$generator)()));
    }

    public function test_registers_middleware(): void
    {
        $this->assertHasMiddlewareAlias('token.validate', TokenValidateMiddleware::class);
        $this->assertHasMiddlewareAlias('token.consume', TokenConsumeMiddleware::class);
    }

    public function test_registers_route_binding_by_default(): void
    {
        static::assertIsCallable($this->app->make(BindingRegistrar::class)->getBindingCallback('token'));
    }
}
