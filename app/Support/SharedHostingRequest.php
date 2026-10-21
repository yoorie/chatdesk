<?php

namespace App\Support;

/**
 * Shared hosting often leaves the project in the domain folder and rewrites
 * every URL into public/ with the root .htaccess. Apache then reports
 * SCRIPT_NAME as /public/index.php, or /shop/public/index.php when the
 * project sits in a subfolder. The browser path does not contain that
 * internal /public segment, so Symfony cannot match it and treats the base
 * path as empty. A request for /shop/login is then routed as /shop/login
 * and every page 404s.
 *
 * Rewrite the front-controller name to /index.php or /shop/index.php before
 * Laravel captures the request. Leave the name alone when the browser really
 * asked for /public/... (the root .htaccess serves that path as-is).
 */
class SharedHostingRequest
{
    public static function normalize(): void
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (! str_ends_with($script, '/public/index.php')) {
            return;
        }

        $appBase = substr($script, 0, -strlen('/public/index.php'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        $publicPrefix = ($appBase === '' ? '' : $appBase).'/public';
        if ($path === $publicPrefix || str_starts_with($path, $publicPrefix.'/')) {
            return;
        }

        $front = ($appBase === '' ? '' : $appBase).'/index.php';
        $_SERVER['SCRIPT_NAME'] = $front;
        if (isset($_SERVER['PHP_SELF'])) {
            $_SERVER['PHP_SELF'] = preg_replace(
                '#/public/index\.php#',
                '/index.php',
                str_replace('\\', '/', (string) $_SERVER['PHP_SELF']),
                1
            );
        }

        // /shop (no slash) does not start with the directory prefix /shop/.
        if ($appBase !== '' && $path === $appBase) {
            $query = parse_url($uri, PHP_URL_QUERY);
            $_SERVER['REQUEST_URI'] = $appBase.'/'.(is_string($query) && $query !== '' ? '?'.$query : '');
        }
    }
}
