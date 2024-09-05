<?php

namespace Tests;

use Illuminate\Support\Carbon;
use Laragear\TokenAction\Store;
use Laragear\TokenAction\Token;
use Mockery;
use UnexpectedValueException;

class TokenTest extends TestCase
{
    protected Mockery\MockInterface|Store $store;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function (): void {
            $this->store = $this->mock(Store::class);
        });

        parent::setUp();
    }

    public function test_instancing_token_with_empty_id_throws_exception(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The Token ID cannot be empty.');

        new Token($this->store, Carbon::now()->toImmutable(), '');
    }

    public function test_tries(): void
    {
        $token = new Token($this->store, Carbon::now()->toImmutable(), 'test_id');

        static::assertSame(0, $token->tries());
    }

    public function test_consume(): void
    {
        $this->store->expects('consume')->with('test_id', 1)->once()->andReturn(0);

        $token = new Token($this->store, Carbon::now()->toImmutable(), 'test_id');

        $token->consume();

        static::assertSame(0, $token->tries());
    }

    public function test_consume_many_tries(): void
    {
        $this->store->expects('consume')->with('test_id', 5)->once()->andReturn(3);

        $token = new Token($this->store, Carbon::now()->toImmutable(), 'test_id', 10);

        $token->consume(5);

        static::assertSame(3, $token->tries());
    }

    public function test_deletes_token(): void
    {
        $this->store->expects('destroy')->with('test_id')->once();

        $token = new Token($this->store, Carbon::now()->toImmutable(), 'test_id', 10);

        $token->delete();
    }

    public function test_casts_token_to_id_string(): void
    {
        $token = new Token($this->store, Carbon::now()->toImmutable(), 'test_id', 10);

        static::assertSame('test_id', (string) $token);
    }
}
