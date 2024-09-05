<?php

namespace Tests;

use DateInterval;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Cache\Factory as FactoryContract;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;
use Laragear\TokenAction\Store;
use Laragear\TokenAction\Token;
use Mockery;
use function now;

class StoreTest extends TestCase
{
    protected Mockery\MockInterface|Repository $cacheStore;
    protected Mockery\MockInterface|FactoryContract $cache;
    protected Store $store;

    protected function setUp(): void
    {
        $this->afterApplicationCreated(function () {
            $this->cacheStore = $this->mock(Repository::class);
            $this->cache = $this->app->instance('cache', $this->mock(FactoryContract::class));
            $this->store = $this->app->make(Store::class);

            $this->freezeSecond();
        });

        parent::setUp();
    }

    public function test_uses_config_store(): void
    {
        $this->app->make('config')->set('token-action.default', 'test_store');

        $this->cacheStore->expects('getMultiple')
            ->with(['token-action|test_id', 'token-action|test_id:tries'])
            ->andReturn(['token-action|test_id' => null, 'token-action|test_id:tries' => null]);

        $this->app->forgetInstance(Store::class);

        $this->cache->expects('store')->with('test_store')->andReturn($this->cacheStore);

        static::assertNull($this->app->make(Store::class)->get('test_id'));
    }

    public function test_uses_config_prefix(): void
    {
        $this->app->make('config')->set('token-action.prefix', 'test_prefix');

        $this->cacheStore->expects('getMultiple')
            ->with(['test_prefix|test_id', 'test_prefix|test_id:tries'])
            ->andReturn(['test_prefix|test_id' => null, 'test_prefix|test_id:tries' => null]);

        $this->app->forgetInstance(Store::class);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        static::assertNull($this->app->make(Store::class)->get('test_id'));
    }

    public function test_uses_custom_store(): void
    {
        $this->cacheStore->expects('getMultiple')
            ->with(['token-action|test_id', 'token-action|test_id:tries'])
            ->andReturn(['token-action|test_id' => null, 'token-action|test_id:tries' => null]);

        $this->cache->expects('store')->with('test_store')->andReturn($this->cacheStore);

        static::assertNull($this->store->store('test_store')->get('test_id'));
    }

    public function test_get_retrieves_token(): void
    {
        $this->cacheStore
            ->expects('getMultiple')
            ->with(['token-action|test_id', 'token-action|test_id:tries'])
            ->andReturn([
                'token-action|test_id' => [
                    'payload' => null,
                    'expires_at' => now()->getTimestamp()
                ],
                'token-action|test_id:tries' => 1
            ]);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $token = $this->store->get('test_id');

        static::assertSame('test_id', $token->id);
        static::assertSame(1, $token->tries());
        static::assertSame(null, $token->payload);
        static::assertEquals(now(), $token->expiresAt);
    }

    public function test_get_returns_null_when_token_doesnt_exists(): void
    {
        $this->cacheStore
            ->expects('getMultiple')
            ->with(['token-action|test_id', 'token-action|test_id:tries'])
            ->andReturn([
                'token-action|test_id' => null,
                'token-action|test_id:tries' => null
            ]);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        static::assertNull($this->store->get('test_id'));
    }

    public function test_get_returns_null_and_destroys_token_when_token_tries_are_zero(): void
    {
        $this->cacheStore
            ->expects('getMultiple')
            ->with(['token-action|test_id', 'token-action|test_id:tries'])
            ->andReturn([
                'token-action|test_id' => [
                    'payload' => null,
                    'expires_at' => now()->getTimestamp()
                ],
                'token-action|test_id:tries' => 0
            ]);

        $this->cacheStore->expects('deleteMultiple')->with(['token-action|test_id', 'token-action|test_id:tries']);

        $this->cache->expects('store')->with(null)->twice()->andReturn($this->cacheStore);

        static::assertNull($this->store->get('test_id'));
    }

    public function test_get_returns_null_and_destroys_token_when_token_tries_are_below_zero(): void
    {
        $this->cacheStore
            ->expects('getMultiple')
            ->with(['token-action|test_id', 'token-action|test_id:tries'])
            ->andReturn([
                'token-action|test_id' => [
                    'payload' => null,
                    'expires_at' => now()->getTimestamp()
                ],
                'token-action|test_id:tries' => -1
            ]);

        $this->cacheStore->expects('deleteMultiple')->with(['token-action|test_id', 'token-action|test_id:tries']);

        $this->cache->expects('store')->with(null)->twice()->andReturn($this->cacheStore);

        static::assertNull($this->store->get('test_id'));
    }

    public function test_puts_token(): void
    {
        $token = new Token($this->store, now()->addMinute()->toImmutable(), 'test_id', 10);

        $this->cacheStore->expects('setMultiple')->with([
            'token-action|test_id' => [
                'payload' => null,
                'expires_at' => $token->expiresAt->getTimestamp()
            ],
            'token-action|test_id:tries' => 10
        ], Mockery::type(DateInterval::class));

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $this->store->put($token);
    }

    public function test_puts_token_with_payload(): void
    {
        $token = new Token($this->store, now()->addMinute()->toImmutable(), 'test_id', 10, true);

        $this->cacheStore->expects('setMultiple')->with([
            'token-action|test_id' => [
                'payload' => true,
                'expires_at' => $token->expiresAt->getTimestamp()
            ],
            'token-action|test_id:tries' => 10
        ], Mockery::type(DateInterval::class));

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $this->store->put($token);
    }

    public function test_destroys_token(): void
    {
        $token = new Token($this->store, now()->addMinute()->toImmutable(), 'test_id', 10, true);

        $this->cacheStore->expects('deleteMultiple')->with(['token-action|test_id', 'token-action|test_id:tries']);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $this->store->destroy($token);
    }

    public function test_destroys_token_by_its_id(): void
    {
        $this->cacheStore->expects('deleteMultiple')->with(['token-action|test_id', 'token-action|test_id:tries']);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $this->store->destroy('test_id');
    }

    public function test_consumes(): void
    {
        $this->cacheStore->expects('decrement')->with('token-action|test_id:tries', 1);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $this->store->consume('test_id', 1);
    }

    public function test_consumes_by_its_id(): void
    {
        $token = new Token($this->store, now()->addMinute()->toImmutable(), 'test_id', 10, true);

        $this->cacheStore->expects('decrement')->with('token-action|test_id:tries', 1);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $this->store->consume($token, 1);
    }

    public function test_serializes_model_without_relations(): void
    {
        Model::unguard();

        $model = new User(['id' => 1, 'friend_id' => 10]);
        $model->setRelation('friend', new User(['id' => 2]));

        Model::reguard();

        User::resolveRelationUsing('friend', function (User $model) {
            return $model->belongsTo(User::class, 'friend_id');
        });

        $token = new Token($this->store, now()->addMinute()->toImmutable(), 'test_id', 10, $model);

        $this->cacheStore->expects('setMultiple')->withArgs(function (array $keys): bool {
            /** @var \Illuminate\Contracts\Database\ModelIdentifier $identifier */
            $identifier = $keys['token-action|test_id']['payload'];

            static::assertInstanceOf(ModelIdentifier::class, $identifier);
            static::assertSame(User::class, $identifier->class);
            static::assertSame(1, $identifier->id);
            static::assertEmpty($identifier->relations);

            return true;
        });

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $this->store->put($token);
    }

    public function test_unserializes_model(): void
    {
        $identifier = new ModelIdentifier(User::class, 1, [], null, null);

        $this->cacheStore->expects('getMultiple')->with(['token-action|test_id', 'token-action|test_id:tries'])
            ->andReturn([
                'token-action|test_id' => [
                    'payload' => $identifier,
                    'expires_at' => now()->getTimestamp()
                ],
                'token-action|test_id:tries' => 1
            ]);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $builder = Mockery::mock(Builder::class);
        $builder->expects('from')->with('users')->times(3)->andReturnSelf();
        $builder->expects('where')->with('users.id', '=', 1)->andReturnSelf();
        $builder->expects('useWritePdo')->andReturnSelf();
        $builder->expects('take')->with(1)->andReturnSelf();
        $builder->expects('get')->andReturn(new Collection([
            (object) [
                'id' => 1,
                'foo' => 'bar'
            ]
        ]));
        $builder->expects('getConnection->getName')->andReturn('test_connection');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->expects('query')->times(3)->andReturn($builder);

        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->expects('connection')->times(3)->andReturn($connection);

        Model::setConnectionResolver($resolver);

        $token = $this->store->get('test_id');

        static::assertInstanceOf(User::class, $token->payload);
        static::assertSame(1, $token->payload->id);
        static::assertSame('bar', $token->payload->foo);
    }

    public function test_serializes_eloquent_collection(): void
    {
        Model::unguard();

        $model = new User(['id' => 1, 'friend_id' => 10]);
        $model->setRelation('friend', new User(['id' => 2]));

        $collection = new EloquentCollection([$model]);

        Model::reguard();

        User::resolveRelationUsing('friend', function (User $model) {
            return $model->belongsTo(User::class, 'friend_id');
        });

        $token = new Token($this->store, now()->addMinute()->toImmutable(), 'test_id', 10, $collection);

        $this->cacheStore->expects('setMultiple')->withArgs(function (array $keys): bool {
            /** @var \Illuminate\Contracts\Database\ModelIdentifier $identifier */
            $identifier = $keys['token-action|test_id']['payload'];

            static::assertInstanceOf(ModelIdentifier::class, $identifier);
            static::assertSame(User::class, $identifier->class);
            static::assertSame([1], $identifier->id);
            static::assertEmpty($identifier->relations);

            return true;
        });

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $this->store->put($token);
    }

    public function test_unserializes_collection(): void
    {
        $identifier = new ModelIdentifier(User::class, [1], [], null, null);

        $this->cacheStore->expects('getMultiple')->with(['token-action|test_id', 'token-action|test_id:tries'])
            ->andReturn([
                'token-action|test_id' => [
                    'payload' => $identifier,
                    'expires_at' => now()->getTimestamp()
                ],
                'token-action|test_id:tries' => 1
            ]);

        $this->cache->expects('store')->with(null)->andReturn($this->cacheStore);

        $builder = Mockery::mock(Builder::class);
        $builder->expects('from')->with('users')->times(2)->andReturnSelf();
        $builder->expects('whereIntegerInRaw')->with('users.id', [1])->andReturnSelf();
        $builder->expects('useWritePdo')->andReturnSelf();
        $builder->expects('get')->andReturn(new Collection([
            (object) [
                'id' => 1,
                'foo' => 'bar'
            ]
        ]));
        $builder->expects('getConnection->getName')->andReturn('test_connection');

        $connection = Mockery::mock(ConnectionInterface::class);
        $connection->expects('query')->times(2)->andReturn($builder);

        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $resolver->expects('connection')->times(2)->andReturn($connection);

        Model::setConnectionResolver($resolver);

        $token = $this->store->get('test_id');

        static::assertInstanceOf(EloquentCollection::class, $token->payload);
        static::assertCount(1, $token->payload);
        static::assertSame(1, $token->payload->first()->id);
        static::assertSame('bar', $token->payload->first()->foo);
    }
}
