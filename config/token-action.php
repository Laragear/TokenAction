<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default store
    |--------------------------------------------------------------------------
    |
    | Tokens are saved on a "store", which by default is defined here. You may
    | change it using the environment variable, or hard-code a defined store.
    | The name should coincide with one defined store name defined as below.
    |
    */

    'default' => env('TOKEN_ACTION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Prefix
    |--------------------------------------------------------------------------
    |
    | When saving the tokens into the cache, these are added a prefix to avoid
    | conflicting with other keys in the cache store. This prefix is fine on
    | most apps, but if this is already being used, change it accordingly.
    |
    */

    'prefix' => env('TOKEN_ACTION_PREFIX', 'token-action'),

    /*
    |--------------------------------------------------------------------------
    | Middleware names
    |--------------------------------------------------------------------------
    |
    | This library registers two convenience middleware to handle Tokens in the
    | route: one for validate if it exists, and another to consume it. Since
    | their aliases can conflict with other middleware, change them here.
    */

    'middleware' => [
        'validate' => 'token.validate',
        'consume' => 'token.consume',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route binding string
    |--------------------------------------------------------------------------
    |
    | Tokens can be resolved in your route actions by using this key name as
    | part of the route declaration. If it collides with other bound keys,
    | like a Model or other bound key, freely change for something else.
    |
    | Remember that this should match the variable name in your route action.
    | For example, `token_action` should be type hinted as `$tokenAction`.
    | Setting this key to `false` or `null` will disable route binding.
    */

    'binding' => 'token',
];
