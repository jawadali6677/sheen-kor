<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    /*
    | The mobile app authenticates with personal access tokens only. An empty
    | list means a website session cookie is never treated as an API login.
    */
    'stateful' => [],

    /*
    | Guards listed here are checked before the bearer token. None are listed,
    | so a website session cannot authorize an API request.
    */
    'guard' => [],

    /*
    | Tokens stay valid until logout, password change, password reset, account
    | disable, or account deletion. There is no time-based expiry.
    */
    'expiration' => null,

    /*
    | Skip Sanctum's first-party /sanctum/csrf-cookie route. The app does not
    | use cookie authentication.
    */
    'routes' => false,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
