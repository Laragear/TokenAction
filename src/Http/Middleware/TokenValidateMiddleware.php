<?php

namespace Laragear\TokenAction\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laragear\TokenAction\Builder;
use Laragear\TokenAction\Store;
use Laragear\TokenAction\Token;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TokenValidateMiddleware
{
    /**
     * The default name of the token key in the URL query.
     *
     * @var string
     */
    protected const QUERY_KEY = 'token';

    /**
     * Create a new Middleware instance.
     */
    public function __construct(protected Builder $builder, protected ?Token $token = null)
    {
        //
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $key = self::QUERY_KEY): mixed
    {
        // Find the token in the request
        $this->token = $this->builder->findOrFail($request->query($key, ''));

        return $next($request);
    }
}
