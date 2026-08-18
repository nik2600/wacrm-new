<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

/*
| ─── THE ONE LOGGING SWITCH ──────────────────────────────────────────────
| LOG_LEVEL in .env is the ONLY control for logging in this app. There is no
| second switch anywhere else — a kill switch used to also live in
| AppServiceProvider::register(), which meant logs could be off for two
| different reasons and you had to check both files to find out why. It was
| removed; this is now the single source of truth.
|
|   LOG_LEVEL=off      → logging is completely DISABLED. Nothing is written to
|                        storage/logs — not info, not warning, not error, not
|                        exceptions. Every Log::* call is routed to the `null`
|                        channel and discarded.
|   LOG_LEVEL=debug    → log EVERYTHING (debug, info, notice, warning, error…).
|   LOG_LEVEL=error    → quiet but still diagnosable: errors only.
|   (unset)            → defaults to `debug`.
|
| Accepted "off" spellings: off, none, false, 0, disabled — so it's hard to get
| wrong on a client server.
|
| TRADE-OFF, read before setting it to off: a silent production box gives you
| nothing to debug from. A crash, a failed send, a rejected Meta template — all
| vanish. Laravel still SHOWS errors (APP_DEBUG / error pages) and still returns
| 500s; they just are not recorded. Prefer LOG_LEVEL=error over off if you want
| quiet-but-diagnosable.
*/
$appLogLevel = strtolower(trim((string) env('LOG_LEVEL', 'debug')));

$loggingDisabled = in_array($appLogLevel, ['off', 'none', 'false', '0', 'disabled'], true);

// When disabled, the level is irrelevant — but it still has to be a level
// Monolog understands, because the channel definitions below are built either
// way (they're just never reached through the `null` default).
if ($loggingDisabled) {
    $appLogLevel = 'emergency';
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    // LOG_LEVEL=off forces the `null` channel here — this is what actually
    // disables logging, and it overrides LOG_CHANNEL on purpose so a stale
    // LOG_CHANNEL=stack in .env can't quietly switch logging back on.
    'default' => $loggingDisabled ? 'null' : env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => $appLogLevel,
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => $appLogLevel,
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => $appLogLevel,
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => $appLogLevel,
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => $appLogLevel,
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => $appLogLevel,
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
