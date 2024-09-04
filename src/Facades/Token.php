<?php

namespace Laragear\TokenAction\Facades;

use Illuminate\Support\Facades\Facade;
use Laragear\TokenAction\Builder;

/**
 * @method static \Laragear\TokenAction\Builder store(?string $store)
 * @method static \Laragear\TokenAction\Builder action(string $action)
 * @method static \Laragear\TokenAction\Builder tries(int $tries)
 * @method static \Laragear\TokenAction\Builder with(mixed $with)
 * @method static \Laragear\TokenAction\Builder as(\Closure|string $as)
 * @method static \Laragear\TokenAction\Builder when(mixed $value = null, ?callable $callback = null, ?callable $default = null)
 * @method static \Laragear\TokenAction\Builder unless(mixed $value = null, ?callable $callback = null, ?callable $default = null)
 * @method static \Laragear\TokenAction\Builder tap(\Closure $callback = null)
 * @method static \Laragear\TokenAction\Token until(\DateTimeInterface|int|string $expires)
 * @method static \Laragear\TokenAction\Token|null find(string $id)
 * @method static \Laragear\TokenAction\Token findOrFail(string $id)
 * @method static \Laragear\TokenAction\Token|null consume(string $id, string $action = null, int $amount = 1)
 * @method static \Laragear\TokenAction\Token consumeOrFail(string $id, string $action = null, int $amount = 1)
 * @method static void destroy(string $id)
 */
class Token extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return Builder::class;
    }
}
