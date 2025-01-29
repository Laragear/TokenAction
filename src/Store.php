<?php

namespace Laragear\TokenAction;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as FactoryContract;
use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Contracts\Queue\QueueableCollection;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

class Store
{
    /**
     * Create a new Store instance.
     */
    public function __construct(protected FactoryContract $cache, protected ?string $store, protected string $prefix)
    {
        //
    }

    /**
     * Changes the cache store to use to manage Tokens.
     *
     * @return $this
     */
    public function store(?string $store): static
    {
        $this->store = $store;

        return $this;
    }

    /**
     * Return the cache key to use.
     */
    protected function getKey(string $id): string
    {
        return trim($this->prefix).'|'.$id;
    }

    /**
     * Retrieve a token from the repository.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get(string $id): ?Token
    {
        if (!$id) {
            return null;
        }

        $tokenId = $this->getKey($id);

        $keys = $this->cache->store($this->store)->getMultiple([$tokenId, "$tokenId:tries"]);

        if ($keys["$tokenId:tries"] < 1) {
            if ($keys[$tokenId]) {
                $this->destroy($id);
            }

            return null;
        }

        return new Token(
            $this,
            CarbonImmutable::createFromTimestamp($keys[$tokenId]['expires_at']),
            $id,
            $keys["$tokenId:tries"],
            $this->getRestoredPropertyValue($keys[$tokenId]['payload']),
        );
    }

    /**
     * Stores a token into the repository.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function put(Token $token): void
    {
        $id = $this->getKey($token->id);

        $this->cache->store($this->store)->setMultiple([
            $id => [
                'payload' => $this->getSerializedPropertyValue($token->payload),
                'expires_at' => $token->expiresAt->getTimestamp(),
            ],
            "$id:tries" => $token->tries()
        ], $token->expiresAt->diff('now', true));
    }

    /**
     * Deletes a token from the repository.
     */
    public function destroy(Token|string $id): void
    {
        $id = $this->getKey((string) $id);

        $this->cache->store($this->store)->deleteMultiple([$id, "$id:tries"]);
    }

    /**
     * Consumes the tries from a token a given number of amount.
     */
    public function consume(Token|string $id, int $amount): int
    {
        $id = $this->getKey((string) $id);

        return (int) $this->cache->store($this->store)->decrement("$id:tries", $amount);
    }

    /**
     * Get the property value prepared for serialization.
     *
     * This function is based on a file from the laravel/framework library.
     *
     * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php
     * @param  mixed  $value
     * @return mixed
     */
    protected function getSerializedPropertyValue(mixed $value): mixed
    {
        if ($value instanceof QueueableCollection) {
            return (new ModelIdentifier(
                $value->getQueueableClass(), $value->getQueueableIds(), [], $value->getQueueableConnection()
            ))->useCollectionClass(
                ($collectionClass = get_class($value)) !== EloquentCollection::class ? $collectionClass : null // @phpstan-ignore-line
            );
        }

        if ($value instanceof QueueableEntity) {
            return new ModelIdentifier(
                get_class($value), $value->getQueueableId(), [], $value->getQueueableConnection() // @phpstan-ignore-line
            );
        }

        return $value;
    }

    /**
     * Get the restored property value after deserialization.
     *
     * This function is based on a file from the laravel/framework library.
     *
     * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getRestoredPropertyValue($value)
    {
        if (! $value instanceof ModelIdentifier) {
            return $value;
        }

        return is_array($value->id)
            ? $this->restoreCollection($value)
            : $this->restoreModel($value);
    }

    /**
     * Restore a queueable collection instance.
     *
     * This function is based on a file from the laravel/framework library.
     *
     * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php
     */
    protected function restoreCollection(ModelIdentifier $value): Collection
    {
        // @codeCoverageIgnoreStart
        if (! $value->class || !count($value->id)) {
            return ! ($value->collectionClass ?? null)
                ? new $value->collectionClass
                : new EloquentCollection;
        }
        /// @codeCoverageIgnoreEnd

        $collection = $this->getQueryForModelRestoration(
            (new $value->class)->setConnection($value->connection), $value->id
        )->useWritePdo()->get();

        // @codeCoverageIgnoreStart
        if (is_a($value->class, Pivot::class, true) || in_array(AsPivot::class, class_uses($value->class), true)) {
            return $collection;
        }
        // @codeCoverageIgnoreEnd

        $collection = $collection->keyBy(static function (Model $model): int|string { // @phpstan-ignore-line
            return $model->getKey();
        });

        /** @var class-string $collectionClass */
        $collectionClass = get_class($collection); // @phpstan-ignore-line

        return new $collectionClass(
            Collection::make($value->id)->map(static function ($id) use ($collection): mixed {
                return $collection[$id] ?? null;
            })->filter()
        );
    }

    /**
     * Restore the model from the model identifier instance.
     *
     * This function is based on a file from the laravel/framework library.
     *
     * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php
     */
    protected function restoreModel(ModelIdentifier $value): Model
    {
        return $this->getQueryForModelRestoration(
            (new $value->class)->setConnection($value->connection), $value->id
        )->useWritePdo()->firstOrFail()->load($value->relations ?? []); // @phpstan-ignore-line
    }

    /**
     * Get the query for model restoration.
     *
     * This function is based on a file from the laravel/framework library.
     *
     * @see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Queue/SerializesAndRestoresModelIdentifiers.php
     */
    protected function getQueryForModelRestoration(Model $model, Arrayable|array|int $ids): Builder
    {
        return $model->newQueryForRestoration($ids);
    }
}
