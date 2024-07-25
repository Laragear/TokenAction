<?php

namespace Tests\Http\Middleware;

use Illuminate\Support\Facades\Route;
use Laragear\TokenAction\Builder;
use Tests\TestCase;

class TokenConsumeMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post('party', function () {
            return true;
        })->middleware('token.consume');
    }

    public function test_existing_token_is_validated_and_consumed(): void
    {
        $builder = $this->app->make(Builder::class);

        $token = $builder->until(10);

        $this->postJson("party?token=$token")->assertOk();

        static::assertNull($builder->find($token->id));
    }

    public function test_existing_token_is_validate_and_consumed_many_times(): void
    {
        $builder = $this->app->make(Builder::class);

        $token = $builder->tries(10)->until(10);

        $this->postJson("party?token=$token")->assertOk();

        $token = $builder->find($token->id);

        static::assertSame(9, $token->tries());
    }

    public function test_absent_token_is_not_found(): void
    {
        $this->postJson('party?token=')->assertNotFound();
        $this->postJson('party')->assertNotFound();
    }

    public function test_existing_token_is_validated_and_consumed_with_custom_key(): void
    {
        Route::post('custom', function () {
            return true;
        })->middleware('token.consume:test_key');

        $builder = $this->app->make(Builder::class);

        $token = $builder->until(10);

        $this->postJson("custom?test_key=$token")->assertOk();

        static::assertNull($builder->find($token->id));
    }

    public function test_existing_token_is_validate_and_consumed_many_times_with_custom_key(): void
    {
        Route::post('custom', function () {
            return true;
        })->middleware('token.consume:test_key');

        $builder = $this->app->make(Builder::class);

        $token = $builder->tries(10)->until(10);

        $this->postJson("custom?test_key=$token")->assertOk();

        $token = $builder->find($token->id);

        static::assertSame(9, $token->tries());
    }

    public function test_absent_token_is_not_found_with_custom_key(): void
    {
        Route::post('custom', function () {
            return true;
        })->middleware('token.consume:test_key');

        $this->postJson('custom?test_key=')->assertNotFound();
        $this->postJson('custom')->assertNotFound();
    }

    public function test_uses_middleware_tries(): void
    {
        Route::post('custom', function () {
            return true;
        })->middleware('token.consume:9');

        $builder = $this->app->make(Builder::class);

        $token = $builder->tries(10)->until(10);

        $this->postJson("custom?token=$token")->assertOk();

        $token = $builder->find($token->id);

        static::assertSame(1, $token->tries());
    }

    public function test_uses_middleware_tries_with_custom_key(): void
    {
        Route::post('custom', function () {
            return true;
        })->middleware('token.consume:test_key,9');

        $builder = $this->app->make(Builder::class);

        $token = $builder->tries(10)->until(10);

        $this->postJson("custom?test_key=$token")->assertOk();

        $token = $builder->find($token->id);

        static::assertSame(1, $token->tries());
    }
}
