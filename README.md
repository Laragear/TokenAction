# Token Action
[![Latest Version on Packagist](https://img.shields.io/packagist/v/laragear/token-action.svg)](https://packagist.org/packages/laragear/token-action)
[![Latest stable test run](https://github.com/Laragear/TokenAction/workflows/Tests/badge.svg)](https://github.com/Laragear/TokenAction/actions)
[![Codecov Coverage](https://codecov.io/gh/Laragear/TokenAction/graph/badge.svg?token=YjJpdKrUCr)](https://codecov.io/gh/Laragear/TokenAction)
[![Maintainability](https://qlty.sh/badges/182c5742-e08b-4049-9624-2bc6980869d9/maintainability.svg)](https://qlty.sh/gh/Laragear/projects/TokenAction)
[![Sonarcloud Status](https://sonarcloud.io/api/project_badges/measure?project=Laragear_TokenAction&metric=alert_status)](https://sonarcloud.io/dashboard?id=Laragear_TokenAction)
[![Laravel Octane Compatibility](https://img.shields.io/badge/Laravel%20Octane-Compatible-success?style=flat&logo=laravel)](https://laravel.com/docs/11.x/octane#introduction)

Use tokens to accept or reject actions a limited number of times.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::until('tomorrow');

return "Confirm the invite using this code: $token";
```

Use them for one-time actions like confirming invites or voting, which after consumed are no longer valid.

## Keep this package free

[![](.github/assets/support.png)](https://github.com/sponsors/DarkGhostHunter)

Your support allows me to keep this package free, up-to-date and maintainable. Alternatively, you can **spread the word on social media**.

## Installation

Fire up Composer and require this package in your project.

```shell
compose require laragear/token-action
```

That's it.

## How it works

Tokens are persisted in your application Cache using a randomly generated key and a default prefix. 

By default, Token Action will use the default Laravel cache. You may set a custom cache using the `TOKEN_ACTION_STORE` environment variable, for example, to use a persistent cache (like `database`, `file` or `redis`) instead of ephemeral ones like `memcache` or `array`. 

```dotenv
TOKEN_ACTION_STORE=file
```

Tokens have a number of "tries" available. When a token reaches 0 tries, is deleted from the cache.

## Creating Tokens

Tokens can be created using the `until()` method of the `Token` facade, along with the moment in time it should expire. You may use an amount of minutes, a `\DateTimeInterface` like a Carbon instance, or a string to parse by [`strtotime()`](https://www.php.net/manual/function.strtotime.php).

```php
use Laragear\TokenAction\Facades\Token;

// Create a token for 10 minutes
$token = Token::until(10);

// Create a token for tomorrow
$token = Token::until('tomorrow');

// Create a token for a specific moment of time
$token = Token::until(now()->addHour());
```

You will receive a `Laragear\TokenAction\Token` instance, already persisted in the database, with a random ID accessible as the `id` property. 

You may use the ID string to, for example, send it in email or to be part of a URL parameter. The Token instance is _castable_ to the ID string, so you can safely output it as text if you need to.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::until('tomorrow');

return route('party.invite', [
    'party' => 420,
    'user_id' => 10,
    'token' => (string) $token,
]);

// https://myapp.com/party/420/invite?user_id=10&token=e9f83d...
```

### Multiple-use tokens

Tokens are single use by default, but you can create tokens that can be consumed a limited number of times using the `tries()` method along with the number of tries.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::tries(2)->until('tomorrow');
```

Tokens can later be [_consumed more than once_](#consuming-more-than-once).

### Payloads

Tokens can be saved with a payload through the `with()` method, like an array or a string.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::with(['cool' => true])->until('tomorrow');
```

You may also add an Eloquent Model or Eloquent Collection as a payload. These are serialized using their primary key and without relations to avoid hitting cache size constraints.

```php
use Laragear\TokenAction\Facades\Token;
use App\Models\Tour;
use App\Models\Party;

// Use a single model
$token = Token::with(Party::find(420))->until('tomorrow');

// Use a collection
$token = Token::with(Tour::limit(10)->get())->until('tomorrow');
```

After you [retrieve the token](#retrieving-tokens), the payload will be included as the `payload` property.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::find('e9f83d...');

if ($token) {
    $party = $token->payload->party->name;
    
    return "You confirmed the invitation to $party";
}

return 'You need to be invited the first place.';
```

> [!IMPORTANT]
>
> Token payloads are read-only. If you need to change the payload, consider cloning the data.

## Retrieving tokens

The most straightforward way to use Tokens is to call the `consume()` method of the `Token` facade. If the token ID exist, has not expired, and has at least 1 try left, it will be returned as a `Token` instance, otherwise it will return `null`.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::consume('e9f83d...');

if ($token) {
    return 'You are confirmed your invitation to the party!'.
}
```

If you want to _fail_ if the token is not found, use the `consumeOrFail()`, which returns an HTTP 404 (Not Found) exception.

```php
use Laragear\TokenAction\Facades\Token;

Token::consumeOrFail('e9f83d...');

return 'You are confirmed your invitation to the party #420';
```

### Finding a token

If you need to retrieve a token without consuming it, use the `find()` method of the `Token` facade. If the token exists, and has tries, you will receive a `Laragear\TokenAction\Token` instance, otherwise `null` will be returned.

After the token is retrieved, **you should use the `consume()` method to actually consume the token**. If the token has many tries, consuming it once will subtract one from the number of tries.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::find('e9f83d...');

if ($token?->consume()) {
    return 'Assistance confirmed!';
}

return 'You are not invited';
```

If you want to find a token or fail by returning an HTTP 404 (Not found) exception, use the `findOrFail()` method with the token id.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::findOrFail('e9f83d...');

$token->consume();

return 'Assistance confirmed!';
```

### Route binding

If you want to retrieve a token as part of your route action, use the `token` as route key to bind it. In your route action you should type hint the Token as `$token`.

As with models, if the token doesn't exist or has expired, an HTTP 404 (Not Found) exception will be thrown.

```php
use App\Models\Party;
use Illuminate\Support\Facades\Route;
use Laragear\TokenAction\Token;

Route::get('party/{party}/invitation/{token}', function (Party $party, Token $token) {
    return view('party.confirm')->with([
        'party' => $party,
        'token' => $token,
    ]);
})
```

If the `token` route key is already used by your application, you can [change in the configuration](#route-binding-key).

## Deleting tokens

The only way to delete a token is knowing its ID. If you have a `Token` instance, you can use the `delete()` method.

```php
use Laragear\TokenAction\Facades\Token;

$token = Token::find('e9f83d...');

if ($token) {
    $token->delete();
}
```

You may also use the `destroy()` method of the `Token` facade with the ID of the token.

```php
use Laragear\TokenAction\Facades\Token;

Token::destroy('e9f83d...');
```

## Cache Store

You can change the cache store to use for managing tokens at runtime with the `store()` method.

```php
use Laragear\TokenAction\Facades\Token;

$partyToken = Token::store('redis')->find('e9f83d...');

$dateToken = Token::store('memcached')->find('e9f83d...');
```

Alternatively, you can change the [default cache store](#cache) to use.

## Middleware

This package comes with two middlewares, `token.validate` and `token.consume`.

The `token.validate` middleware checks if a token exists, but doesn't consume it. This is great, for example, to show a view with a form as long the token has enough tries and has not expired.

```php
use Illuminate\Support\Facades\Route;
use App\Models\Party;

// Show the form to confirm the invitation.
Route::get('{party}/confirm', function (Party $party) {
    return view('party.confirm')->with('party', $party);
})->middleware('token.validate');
```

On the other hand, the `token.consume` middleware automatically consumes a token from the parameters URL once a successful response is returned. In other words, if the response is successful (HTTP 2XX) or a redirection (HTTP 3XX), the token is consumed.

This should be used, for example, when receiving a form submission from the frontend.

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Party;

// Show the form to confirm the invitation.
Route::get('{party}/confirm', function (Party $party) {
    // ...
})->middleware('token.validate');

// Handle the form submission.
Route::post('{party}/confirm', function (Request $request, Party $party) {
	$party->confirmInviteForUser($request->user());
	
	return back();
})->middleware('token.consume');
```

> [!IMPORTANT]
>
> These middleware work over the **query URL exclusively**. If you have set the token outside the query URL, you should check that manually in your route action.

### Token parameter Key

Both `token.validate` and `token.consume` middleware try to find the token in the `token` URL parameter. If the token resides in another key, you can point it out as an argument. 

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Party;

Route::get('{party}/confirm', function (Party $party) {
	// ...
})->middleware('token.validate:token-action');

Route::post('{party}/confirm', function (Request $request, Party $party) {
	// ...
})->middleware('token.consume:token-action');
```

### Consuming more than once

When using the `token.consume` middleware, tokens are consumed exactly 1 time. You may change it by setting a number as last middleware argument.

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Party;

Route::post('{party}/confirm', function (Request $request, Party $party) {
	// ...
})->middleware('token.consume:2');
```

## ID Generator

By default, a token ID is an ULID, created by [`Str::ulid()`](https://laravel.com/docs/11.x/strings#method-str-ulid). You can change it for anything by using the `as()` method at runtime. It accepts a string, or a Closure that returns a string.

```php
use Laragear\TokenAction\Facades\Token;
use Illuminate\Support\Str;

$token = Token::store('redis')->as(Str::random(32))->until(60);
```

Alternatively, you may use the `Token::$generator` static property the `boot()` method of your `AppServiceProvider` with a Closure that returns a random string.

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laragear\TokenAction\Token;

class AppServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Token::$generator = fn() => Str::random(48);
    }
}
```

> [!NOTE]
> 
> The string to generate will be [prefixed](#cache) to avoid cache key collisions.

## Configuration

To further configure the package, publish the configuration file:

```shell
php artisan vendor:publish --provider="Laragear\TokenAction\TokenActionServiceProvider" --tag="config"
```

You will receive the `config/token-action.php` config file with the following contents:

```php
return [
    'default' => env('TOKEN_ACTION_STORE'),
    'prefix' => env('TOKEN_ACTION_PREFIX', 'token-action'),
    'middleware' => [
        'validate' => 'token.validate',
        'consume' => 'token.consume',
    ],
    'bound' => 'token',
]
```

### Cache

```php
return [
    'default' => env('TOKEN_ACTION_STORE'),
    'prefix' => env('TOKEN_ACTION_PREFIX', 'token-action'),
```

The `default` key sets which cache store from the application to use. When it's not set, it will use the default set in the application, which on fresh installations is `file`.

The `prefix` keys is the string used to prefix all keys for the Tokens generated by library.

Instead of changing these values directly in the configuration file, you should use the `TOKEN_ACTION_STORE` and `TOKEN_ACTION_PREFIX` environment variables, respectively.

```dotenv
TOKEN_ACTION_STORE=memcache
TOKEN_ACTION_PREFIX=my-custom-prefix
```

> [!IMPORTANT]
> 
> Ensure you set a fault-tolerant and persistent cache for your Tokens. Using a volatile cache store will prune old tokens even if these should still be valid. A good option is the `file` store, but you may use `database` for maximum reliability, or `redis` compatible store with [persistence](https://redis.io/docs/latest/operate/oss_and_stack/management/persistence/).


### Middleware aliases

```php
return [
    'middleware' => [
        'validate' => 'token.validate',
        'consume' => 'token.consume',
    ],
]
```

[Both middleware](#middleware) aliases are configured here. If you have other middleware with the same aliases, you may change them here. Alternatively, you can always set the middleware in your route by pointing the middleware class.

```php
use Illuminate\Support\Facades\Route;
use Laragear\TokenAction\Http\Middleware\TokenValidateMiddleware;

Route::get('invite', function () {
    // ...
})->middleware(TokenValidateMiddleware::class)
```

### Route binding key

```php
return [
    'bound' => 'token',
]
```

This library registers the `token` string as route key to create an instance of `Token` based on the string id received. While this usually doesn't bring problems, you may have already a Model or another library using that route key for its own class. Here you can change it for non-conflicting key, like `tokenAction`.

## Laravel Octane Compatibility

- There only singleton using a stale application instance is the Token Store.
- There are no singletons using a stale config instance.
- There are no singletons using a stale request instance.
- There are no static properties written during a request.

The Token Store, and its Cache Store instance stored inside, are not meant to be changed during the application lifetime.

Apart from that, there should be no problems using this package with Laravel Octane.

## Security

If you discover any security related issues, please email darkghosthunter@gmail.com instead of using the issue tracker.

### Token swapping

Users may swap an invalid token with a valid one in the URL to bypass token verification. To avoid this, you can either:

- Use the [Token payload](#payloads) to validate the data before proceeding.
- Use a [signed route](https://laravel.com/docs/11.x/urls#signed-urls) to avoid changing the URL parameters.

Depending on the action being used with the Token, one could better than the other. For example, if you expect high request volume, the signed route could be great to not hit the application cache or database. On the other hand, the Token payload can be a great solution if you need complex or private information not suited for a URL Query and always get correct data.

# License

This specific package version is licensed under the terms of the [MIT License](LICENSE.md), at time of publishing.

[Laravel](https://laravel.com) is a Trademark of [Taylor Otwell](https://github.com/TaylorOtwell/). Copyright © 2011-2025 Laravel LLC.
