<?php

namespace Laragear\TokenAction;

use Carbon\CarbonImmutable;
use Closure;
use Stringable;
use UnexpectedValueException;

class Token implements Stringable
{
    /**
     * The callback used to generate tokens ID.
     *
     * @var \Closure():string
     */
    public static Closure $generator;

    /**
     * Create a new Token instance.
     */
    public function __construct(
        readonly protected Store $store,
        readonly public CarbonImmutable $expiresAt,
        readonly public string $id,
        protected int $tries = 0,
        readonly public mixed $payload = null,
    ) {
        if (!$this->id) {
            throw new UnexpectedValueException('The Token ID cannot be empty.');
        }
    }

    /**
     * Returns the number of tries.
     */
    public function tries(): int
    {
        return $this->tries;
    }

    /**
     * Consumes the token.
     */
    public function consume(int $amount = 1): void
    {
        $this->tries = $this->store->consume($this->id, $amount);
    }

    /**
     * Deletes the token from the repository.
     */
    public function delete(): void
    {
        // We can simply consume an infinite amount of tries to ensure it dies.
        $this->store->destroy($this->id);
    }

    /**
     * Returns string representation of the object.
     */
    public function __toString(): string
    {
        return $this->id;
    }
}
