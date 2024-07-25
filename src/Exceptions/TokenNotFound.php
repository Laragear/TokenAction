<?php

namespace Laragear\TokenAction\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function sprintf;

/**
 * @internal
 */
class TokenNotFound extends NotFoundHttpException
{
    /**
     * The message template to use.
     *
     * @var string
     */
    public const MESSAGE_TEMPLATE = 'The token [%s] was not found.';

    /**
     * @var string
     */
    protected string $id;

    /**
     * Return the Token ID that wasn't found.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param  string  $id
     */
    public function __construct(string $id)
    {
        $this->id = $id;

        parent::__construct(sprintf(static::MESSAGE_TEMPLATE, $id));
    }
}
