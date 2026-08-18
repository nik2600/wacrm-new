<?php

/*
|--------------------------------------------------------------------------
| Developer file-sync
|--------------------------------------------------------------------------
| Secret for the private /wd-sync deploy endpoint (WdSyncController).
| EMPTY = endpoint disabled (returns 404). Set WD_SYNC_KEY in .env to a long
| random string to turn it on. Reading it through config() — not env() directly
| in the controller — keeps it working after `php artisan config:cache`.
*/

return [
    'key' => env('WD_SYNC_KEY', ''),
];
