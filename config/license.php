<?php

/*
|--------------------------------------------------------------------------
| WaDesk licence (Envato)
|--------------------------------------------------------------------------
| CodeCanyon item id + author personal token used by the installer and the
| admin Updater to verify buyer purchase codes against the Envato API.
|
| Kept in THIS file on purpose — NOT in .env — so the installer (which writes
| .env) can never wipe it and buyers don't have to configure anything. The
| token is base64-wrapped so the plaintext isn't sitting in the source. To
| rotate it, replace the base64 string below (it is base64 of the raw token).
*/

return [
    'item_id' => '63755235',
    'token'   => base64_decode('aW5OeTgzRlRqVjJDVFBxdk5kUEdScjJtQUowcmFQQzQ='),
];
