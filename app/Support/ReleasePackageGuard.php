<?php

namespace App\Support;

/**
 * bootstrap/cache/packages.php and services.php are written by
 * `php artisan package:discover` on the machine that built them. A dev
 * checkout lists Laravel Pail, Sail, and Collision. Those packages are not
 * in a --no-dev vendor directory, and loading the cache crashes the
 * installer before it can run. The release zip must not contain the files.
 * If a buyer still uploads them, drop a manifest that names a missing class
 * so Laravel can rebuild it from the installed packages.
 */
class ReleasePackageGuard
{
    public const COMPILED = [
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
    ];

    /**
     * @return list<string> Relative paths that are present under $root.
     */
    public static function compiledManifests(string $root): array
    {
        $root = rtrim($root, '/');
        $found = [];
        foreach (self::COMPILED as $relative) {
            if (is_file($root.'/'.$relative)) {
                $found[] = $relative;
            }
        }

        return $found;
    }

    /**
     * Delete packages.php / services.php in $cacheDir when they name a
     * provider class that is not installed. A dev cache is left in place
     * while those classes exist.
     */
    public static function discardUnreadableProviders(string $cacheDir): bool
    {
        $cacheDir = rtrim($cacheDir, '/');
        $discarded = false;

        foreach (['packages.php', 'services.php'] as $name) {
            $path = $cacheDir.'/'.$name;
            if (! is_file($path)) {
                continue;
            }

            if (self::manifestIsReadable($path, $name)) {
                continue;
            }

            self::invalidate($path);
            if (@unlink($path)) {
                $discarded = true;
            }
        }

        return $discarded;
    }

    private static function manifestIsReadable(string $path, string $name): bool
    {
        self::invalidate($path);

        try {
            $data = include $path;
        } catch (\Throwable) {
            return false;
        }

        if (! is_array($data)) {
            return false;
        }

        foreach (self::providerClasses($data, $name) as $class) {
            try {
                if (! class_exists($class)) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    private static function providerClasses(array $data, string $name): array
    {
        $classes = [];

        if ($name === 'services.php') {
            foreach (['providers', 'eager'] as $key) {
                foreach ($data[$key] ?? [] as $class) {
                    if (is_string($class) && str_contains($class, '\\')) {
                        $classes[] = $class;
                    }
                }
            }

            return $classes;
        }

        foreach ($data as $package) {
            if (! is_array($package)) {
                continue;
            }
            foreach ($package['providers'] ?? [] as $class) {
                if (is_string($class) && str_contains($class, '\\')) {
                    $classes[] = $class;
                }
            }
        }

        return $classes;
    }

    private static function invalidate(string $path): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }
}
