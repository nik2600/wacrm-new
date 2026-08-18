<?php

/*
|--------------------------------------------------------------------------
| Customer REST API  (mounted at /api/v1, api-key authed — bootstrap/app.php)
|--------------------------------------------------------------------------
| Public, versioned, documented API for customers/developers. Auth is per-
| workspace API keys (Authorization: Bearer wsk_...). Each resource lives in
| its own file under routes/api/v1/ and is auto-loaded below, so adding a
| resource never touches this file (no merge conflicts).
|
| Paths here are written WITHOUT the /api/v1 prefix — the group in
| bootstrap/app.php adds it.
*/

foreach (glob(base_path('routes/api/v1/*.php')) as $resourceRoutes) {
    require $resourceRoutes;
}
