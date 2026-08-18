<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Installed version
    |--------------------------------------------------------------------------
    | Bumped by each update package. The admin Updater compares the version
    | inside an uploaded ZIP against this before applying.
    */
    'version' => '1.5.0',
    'build'   => 5,

    /*
    |--------------------------------------------------------------------------
    | Envato purchase verification
    |--------------------------------------------------------------------------
    | Item id + author token come from config/license.php — a SHIPPED file,
    | NOT .env — so the installer (which writes .env) can never wipe them and
    | buyers configure nothing. Edit config/license.php to rotate the token.
    */
    'envato' => (static function () {
        $file = __DIR__ . '/license.php';
        $data = is_file($file) ? (array) require $file : [];

        return [
            'item_id' => (string) ($data['item_id'] ?? ''),
            'token'   => (string) ($data['token'] ?? ''),
        ];
    })(),
];
