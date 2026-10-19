<?php

declare(strict_types=1);

/*
 * VoIP (PushKit) pushes, sent directly to Apple instead of through FCM -- the only way to
 * ring a CallKit call screen while an iOS app is swiped away or suspended. See
 * docs/new_IOS_PUSH_BACKEND_GUIDE.md §10.
 */
return [
    /*
     * The APNs auth key (.p8), its Key ID and your Apple Developer Team ID. The same key as
     * uploaded to Firebase console for Cloud Messaging. APNS_AUTH_KEY_PATH is a path to the
     * .p8 file on disk, read at send time -- matching how FIREBASE_CREDENTIALS works above.
     */
    'key_id' => env('APNS_KEY_ID'),
    'team_id' => env('APNS_TEAM_ID'),
    'auth_key_path' => env('APNS_AUTH_KEY_PATH'),

    /*
     * Bundle ID per app, by environment. Which environment a given request targets is
     * decided by app()->isProduction() -- this backend's own environment, not anything the
     * client reports.
     */
    'bundles' => [
        'rider' => [
            'production' => 'com.swiftboda.swiftBodaRider',
            'development' => 'com.swiftboda.swiftBodaRider.dev',
        ],
        'passenger' => [
            'production' => 'com.swiftboda.swiftBoda',
            'development' => 'com.swiftboda.swiftBoda.dev',
        ],
    ],

    'hosts' => [
        'production' => 'api.push.apple.com',
        'development' => 'api.sandbox.push.apple.com',
    ],
];
