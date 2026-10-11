<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Read by Laravel's TrustProxies middleware so $request->ip() resolves the
    | real client IP from X-Forwarded-For instead of the proxy's address.
    | Accepts a comma-separated list of IPs/CIDRs, or "*" to trust the proxy
    | that is calling the app. Leave unset when not behind a proxy, otherwise
    | clients could spoof their IP via the X-Forwarded-For header.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
