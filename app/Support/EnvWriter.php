<?php

namespace App\Support;

/**
 * Writes/updates keys in the project .env so admin-set values (the Node
 * webhook token, the Node Server URL) stay in sync with the DB — the admin
 * never has to hand-edit .env, and DB ↔ env can't drift out of agreement.
 *
 * Safe by design: returns false (never throws) when .env is missing or not
 * writable, so a save can't 500 just because the file is locked down. The DB
 * copy remains the source of truth (node_token() / wd_node_url() read DB
 * first), so this is a convenience mirror — if the write fails the app still
 * works off the DB value.
 *
 * Note: env() values already loaded for the current request don't change
 * mid-request; the .env write is picked up on the next request (and by any
 * CLI/Node process reading the same file). That's why DB stays authoritative.
 */
class EnvWriter
{
    public static function set(string $key, string $value): bool
    {
        try {
            $path = base_path('.env');
            if (! is_file($path) || ! is_writable($path)) {
                return false;
            }
            $content = file_get_contents($path);
            if ($content === false) {
                return false;
            }

            $line = $key . '=' . self::quote($value);

            if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
                // preg_replace_callback so $ / \ in the value are not treated
                // as backreferences.
                $content = preg_replace_callback(
                    '/^' . preg_quote($key, '/') . '=.*/m',
                    static fn () => $line,
                    $content
                );
            } else {
                $content = rtrim($content, "\r\n") . "\n" . $line . "\n";
            }

            return file_put_contents($path, $content) !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Quote only when the value contains whitespace or shell-sensitive chars. */
    private static function quote(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/[\s#"\'\\\\]/', $value)) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        return $value;
    }
}
