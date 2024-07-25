<?php

namespace Laragear\TokenAction\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use function is_numeric;

class TokenConsumeMiddleware extends TokenValidateMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $key = self::QUERY_KEY, int $amount = 1): mixed
    {
        // Swap the arguments if the query key is numeric
        if (is_numeric($key)) {
            [$key, $amount] = [self::QUERY_KEY, (int) $key];
        }

        /** @var \Illuminate\Http\Response $response */
        $response = parent::handle($request, $next, $key);

        if ($response->isSuccessful() || $response->isRedirection()) {
            $this->token->consume($amount);
        }

        return $response;
    }
}
