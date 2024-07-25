<?php

namespace Tests\Http\Middleware;

use Illuminate\Support\Facades\Route;
use Laragear\TokenAction\Builder;
use Tests\TestCase;

class TokenValidateMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->get('party', function () {
            return true;
        })->middleware('token.validate');
    }

    public function test_existing_token_is_validated(): void
    {
        $token = $this->app->make(Builder::class)->until(10);

        $this->getJson("party?token=$token")->assertOk();
    }

    public function test_absent_token_is_not_found(): void
    {
        $this->getJson('party?token=')->assertNotFound();
        $this->getJson('party')->assertNotFound();
    }

    public function test_existing_token_is_validated_in_custom_key(): void
    {
        Route::get('custom', function () {
            return true;
        })->middleware('token.validate:test_key');

        $token = $this->app->make(Builder::class)->until(10);

        $this->getJson("custom?test_key=$token")->assertOk();
    }

    public function test_absent_token_is_not_found_in_custom_key(): void
    {
        Route::get('custom', function () {
            return true;
        })->middleware('token.validate:test_key');

        $this->getJson('custom?test_key=')->assertNotFound();
        $this->getJson('custom')->assertNotFound();
    }
}
