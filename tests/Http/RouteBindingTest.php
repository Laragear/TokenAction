<?php

namespace Tests\Http;

use Laragear\TokenAction\Builder;
use Laragear\TokenAction\Token;
use Tests\TestCase;

class RouteBindingTest extends TestCase
{
    protected function defineWebRoutes($router)
    {
        $router->get('test/{token}', function (Token $token) {
            return $token->id;
        });
    }

    public function test_returns_existing_token(): void
    {
        $token = $this->app->make(Builder::class)->until(10);

        $this->getJson("test/$token")
            ->assertOk()
            ->assertSee($token->id);
    }

    public function test_returns_not_found_if_non_existing_token(): void
    {
        $this->getJson("test/not_found")
            ->assertNotFound();
    }
}
