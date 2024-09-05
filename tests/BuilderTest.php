<?php

namespace Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laragear\TokenAction\Builder;
use Laragear\TokenAction\Exceptions\TokenNotFound;
use Laragear\TokenAction\Store;
use Laragear\TokenAction\Token;
use Mockery;
use ValueError;
use function now;

class BuilderTest extends TestCase
{
    protected Mockery\MockInterface|Store $store;
    protected Builder $builder;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function () {
            $this->store = $this->mock(Store::class);
            $this->builder = $this->app->make(Builder::class);
            $this->freezeSecond();
        });

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Token::$generator = Str::ulid(...);
    }

    public function test_uses_custom_generator(): void
    {
        Token::$generator = fn() => 'test_id';

        $this->store->expects('put');

        $token = $this->builder->until(now()->addSecond());

        static::assertSame('test_id', $token->id);
    }

    public function test_store(): void
    {
        $this->store->expects('store')->with('test');

        $this->builder->store('test');
    }

    public function test_store_null(): void
    {
        $this->store->expects('store')->with(null);

        $this->builder->store(null);
    }

    public function test_tries(): void
    {
        $this->store->expects('put')->withArgs(static function (Token $token): bool {
            return $token->tries() === 10;
        });

        $this->builder->tries(10)->until(now());
    }

    public function test_tries_throws_exception_on_less_than_one_tries(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Cannot assign less than 1 tries to a Token.');

        $this->builder->tries(0);
    }

    public function test_with(): void
    {
        $this->store->expects('put')->withArgs(static function (Token $token): bool {
            return $token->payload;
        });

        $this->builder->with(true)->until(now());
    }

    public function test_as(): void
    {
        $this->store->expects('put')->withArgs(static function (Token $token): bool {
            return 'test-id' === $token->id;
        });

        $this->builder->as('test-id')->until(now());
    }

    public function test_as_with_closure(): void
    {
        $this->store->expects('put')->withArgs(static function (Token $token): bool {
            return 'test-id' === $token->id;
        });

        $this->builder->as(fn() => 'test-id')->until(now());
    }

    public function test_until_given_datetime(): void
    {
        $until = now()->addMinute();

        $this->store->expects('put')->withArgs(static function (Token $token) use ($until): bool {
            return $token->expiresAt->isSameSecond($until);
        });

        $this->builder->until($until);
    }

    public function test_until_given_string(): void
    {
        $until = 'next year';

        $this->store->expects('put')->withArgs(static function (Token $token) use ($until): bool {
            return $token->expiresAt->isSameSecond(Carbon::parse($until));
        });

        $this->builder->until($until);
    }

    public function test_until_given_minutes(): void
    {
        $until = 60;

        $this->store->expects('put')->withArgs(static function (Token $token) use ($until): bool {
            return $token->expiresAt->isSameSecond(now()->addMinutes($until));
        });

        $this->builder->until($until);
    }

    public function test_until_throws_exception_if_past(): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Cannot make a Token already expired.');

        $this->builder->until(now()->subSecond());
    }

    public function test_consume(): void
    {
        $token = new Token($this->store, now()->toImmutable(), 'test_id', 1);

        $this->store->expects('get')->with('test_id')->andReturn($token);
        $this->store->expects('consume')->with('test_id', 1);

        static::assertSame($token, $this->builder->consume('test_id'));
    }

    public function test_consume_non_existent_token(): void
    {
        $this->store->expects('get')->with('test_id')->andReturnNull();

        $this->store->expects('consume')->never();

        static::assertNull($this->builder->consume('test_id', 10));
    }

    public function test_consume_custom_tries(): void
    {
        $token = new Token($this->store, now()->toImmutable(), 'test_id', 1);

        $this->store->expects('get')->with('test_id')->andReturn($token);

        $this->store->expects('consume')->with('test_id', 10);

        static::assertSame($token, $this->builder->consume('test_id', 10));
    }

    public function test_consume_or_fail_passes(): void
    {
        $token = new Token($this->store, now()->toImmutable(), 'test_id', 1);

        $this->store->expects('get')->with('test_id')->andReturn($token);
        $this->store->expects('consume')->with('test_id', 1);

        static::assertSame($token, $this->builder->consumeOrFail('test_id'));
    }

    public function test_consume_or_fail_passes_with_custom_tries(): void
    {
        $token = new Token($this->store, now()->toImmutable(), 'test_id', 10);

        $this->store->expects('get')->with('test_id')->andReturn($token);
        $this->store->expects('consume')->with('test_id', 10);

        static::assertSame($token, $this->builder->consumeOrFail('test_id', 10));
    }

    public function test_consume_or_fail_throws_exception(): void
    {
        $this->store->expects('get')->with('test_id')->andReturnNull();
        $this->store->expects('consume')->never();

        $this->expectException(TokenNotFound::class);
        $this->expectExceptionMessage('The token [test_id] was not found.');

        try {
            $this->builder->consumeOrFail('test_id');
        } catch (TokenNotFound $exception) {
            static::assertSame('test_id', $exception->getId());

            throw $exception;
        }
    }

    public function test_find_existing_token(): void
    {
        $token = new Token($this->store, now()->toImmutable(), 'test_id', 1);

        $this->store->expects('get')->with('test_id')->andReturn($token);

        static::assertSame($token, $this->builder->find('test_id'));
    }

    public function test_find_non_existing_token_returns_null(): void
    {
        $this->store->expects('get')->with('test_id')->andReturnNull();

        static::assertNull($this->builder->find('test_id'));
    }

    public function test_find_or_fail_finds_existing_token(): void
    {
        $token = new Token($this->store, now()->toImmutable(), 'test_id', 1);

        $this->store->expects('get')->with('test_id')->andReturn($token);

        static::assertSame($token, $this->builder->findOrFail('test_id'));
    }

    public function test_find_or_fail_throws_exception_on_non_existing_token(): void
    {
        $this->store->expects('get')->with('test_id')->andReturnNull();

        $this->expectException(TokenNotFound::class);
        $this->expectExceptionMessage('The token [test_id] was not found.');

        try {
            $this->builder->findOrFail('test_id');
        } catch (TokenNotFound $exception) {
            static::assertSame('test_id', $exception->getId());

            throw $exception;
        }
    }

    public function test_destroy(): void
    {
        $this->store->expects('destroy')->with('test_id');

        $this->builder->destroy('test_id');
    }
}
