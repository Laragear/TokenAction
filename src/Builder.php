<?php

namespace Laragear\TokenAction;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Laragear\TokenAction\Exceptions\TokenNotFound;
use ValueError;
use function is_int;
use function value;

class Builder
{
    /**
     * Create a new Builder instance.
     */
    public function __construct(
        protected Store $store,
        protected DateTimeInterface|int|string|null $expires = null,
        protected int $tries = 1,
        protected mixed $with = null,
        protected Closure|string|null $as = null
    )
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
        $this->store->store($store);

        return $this;
    }

    /**
     * Set an amount of tries the token can be consumed.
     *
     * @return $this
     */
    public function tries(int $tries): static
    {
        $this->tries = $tries;

        if ($this->tries < 1) {
            throw new ValueError('Cannot assign less than 1 tries to a Token.');
        }

        return $this;
    }

    /**
     * Add additional payload to the token.
     *
     * @return $this
     */
    public function with(mixed $with): static
    {
        $this->with = $with;

        return $this;
    }

    /**
     * The token ID that should be used to store it, without prefix.
     *
     * @param  (\Closure():string)|string  $token
     * @return $this
     */
    public function as(Closure|string $token): static
    {
        $this->as = $token;

        return $this;
    }

    /**
     * Creates a new Token instance, optionally with a custom payload.
     */
    public function until(DateTimeInterface|int|string $expires): Token
    {
        if (is_int($expires)) {
            $expires = CarbonImmutable::now()->addMinutes($expires);
        }

        $expires = CarbonImmutable::parse($expires);

        if ($expires->isPast()) {
            throw new ValueError('Cannot make a Token already expired.');
        }

        $token = new Token($this->store, $expires, $this->generateTokenId(), $this->tries, $this->with);

        $this->store->put($token);

        return $token;
    }

    /**
     * Generate a token ID.
     */
    protected function generateTokenId(): string
    {
        if ($this->as) {
            return value($this->as);
        }

        if (isset(Token::$generator)) {
            return (Token::$generator)();
        }

        throw new ValueError('The Token ID generator is not set.');
    }

    /**
     * Retrieve and consume the token.
     */
    public function consume(string $id, int $amount = 1): ?Token
    {
        $token = $this->find($id);

        if ($token) {
            $this->store->consume($id, $amount);
        }

        return $token;
    }

    /**
     * Retrieve and consume the token, or fail if the token doesn't exist.
     */
    public function consumeOrFail(string $id, int $amount = 1): mixed
    {
        return $this->consume($id, $amount) ?? throw new TokenNotFound($id);
    }

    /**
     * Finds a token by the given ID.
     */
    public function find(string $id): ?Token
    {
        return $this->store->get($id);
    }

    /**
     * Finds a token by the given ID, or fail if the token doesn't exist.
     */
    public function findOrFail(string $id): Token
    {
        return $this->find($id) ?? throw new TokenNotFound($id);
    }

    /**
     * Delete the token by its ID.
     */
    public function destroy(string $id): void
    {
        $this->store->destroy($id);
    }
}
