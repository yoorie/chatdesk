<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

        <?php
            $brandingService = app(\App\Services\BrandingService::class);
            $platformName = $brandingService->getPlatformName() ?: config('app.name', 'ChatDesk');
            $googleAnalyticsEnabled = (bool) \App\Models\PlatformSetting::get('analytics.google_analytics_enabled', false);
            $googleAnalyticsId = trim((string) \App\Models\PlatformSetting::get('analytics.google_analytics_id', ''));
            $seoRouteMap = [
                'landing' => 'landing',
                'pricing' => 'pricing',
                'privacy' => 'privacy',
                'terms' => 'terms',
                'refund.policy' => 'refund',
                'acceptable.use' => 'acceptableUse',
                'gdpr' => 'gdpr',
                'cookies' => 'cookies',
                'security' => 'security',
                'help' => 'help',
                'faqs' => 'faqs',
                'knowledgebase' => 'knowledgebase',
                'roadmap' => 'roadmap',
                'docs' => 'docs',
                'about' => 'about',
                'contact' => 'contact',
            ];
            $seoPayload = null;
            $seoKey = $seoRouteMap[\Illuminate\Support\Facades\Route::currentRouteName()] ?? null;
            if ($seoKey) {
                try {
                    $seoPayload = app(\App\Services\SeoService::class)->page($seoKey);
                } catch (\Throwable $e) {
                    $seoPayload = null;
                }
            }
        ?>
        <script>window.__APP_BRAND__ = <?php echo json_encode($platformName, 15, 512) ?>;</script>
        <style>
            :root {
                --brand-primary-rgb: <?php echo e($brandingService->colorRgb($brandingService->getPrimaryColor(), '#00A548')); ?>;
                --brand-secondary-rgb: <?php echo e($brandingService->colorRgb($brandingService->getSecondaryColor(), '#053A35')); ?>;
            }
        </style>
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
        <?php if(!isset($__inertiaSsrResponse) || !$__inertiaSsrResponse || !str_contains($__inertiaSsrResponse->head, '<title')): ?>
            <title inertia><?php echo e($seoPayload['title'] ?? $platformName); ?></title>
        <?php endif; ?>

        <?php if($seoPayload): ?>
            <meta name="description" content="<?php echo e($seoPayload['description']); ?>">
            <?php if(! empty($seoPayload['keywords'])): ?>
                <meta name="keywords" content="<?php echo e($seoPayload['keywords']); ?>">
            <?php endif; ?>
            <meta name="robots" content="<?php echo e($seoPayload['robots']); ?>">
            <link rel="canonical" href="<?php echo e($seoPayload['canonical']); ?>">
            <meta property="og:type" content="<?php echo e($seoPayload['type'] ?? 'website'); ?>">
            <meta property="og:title" content="<?php echo e($seoPayload['title']); ?>">
            <meta property="og:description" content="<?php echo e($seoPayload['description']); ?>">
            <meta property="og:url" content="<?php echo e($seoPayload['url']); ?>">
            <meta property="og:site_name" content="<?php echo e($seoPayload['site_name']); ?>">
            <meta property="og:locale" content="<?php echo e($seoPayload['locale']); ?>">
            <meta property="og:image" content="<?php echo e($seoPayload['image']); ?>">
            <meta property="og:image:alt" content="<?php echo e($seoPayload['site_name']); ?> product preview">
            <meta name="twitter:card" content="<?php echo e($seoPayload['twitter_card'] ?? 'summary_large_image'); ?>">
            <meta name="twitter:title" content="<?php echo e($seoPayload['title']); ?>">
            <meta name="twitter:description" content="<?php echo e($seoPayload['description']); ?>">
            <meta name="twitter:image" content="<?php echo e($seoPayload['image']); ?>">
            <?php $__currentLoopData = $seoPayload['schemas'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $schema): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <script type="application/ld+json"><?php echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endif; ?>

        <?php if($googleAnalyticsEnabled && $googleAnalyticsId !== ''): ?>
            <!-- Google tag (gtag.js) -->
            <script id="ga-script" async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e($googleAnalyticsId); ?>"></script>
            <script id="ga-inline">
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', <?php echo json_encode($googleAnalyticsId, 15, 512) ?>);
            </script>
        <?php endif; ?>

        <!-- Favicon (will be overridden by BrandingWrapper if custom favicon is set) -->
        <link rel="icon" type="image/x-icon" href="<?php echo e(asset('favicon.ico')); ?>">
        <link rel="manifest" href="<?php echo e(asset('manifest.json')); ?>">
        <meta name="app-base" content="<?php echo e(rtrim(request()->getBasePath(), '/')); ?>">
        <meta name="theme-color" content="#047857">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">

        <!-- Fonts -->

        <script>
            (function () {
                try {
                    var storedTheme = window.localStorage.getItem('ui-theme') || window.localStorage.getItem('legacy-ui-theme');
                    var legacyDarkMode = window.localStorage.getItem('darkMode');
                    var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var resolvedTheme = storedTheme === 'dark' || (!storedTheme && legacyDarkMode === 'true') || ((!storedTheme || storedTheme === 'system') && legacyDarkMode === null && prefersDark)
                        ? 'dark'
                        : 'light';

                    if (resolvedTheme === 'dark') {
                        document.documentElement.classList.add('dark');
                    }
                    document.documentElement.style.colorScheme = resolvedTheme;
                } catch (e) {
                    // noop
                }
            })();
        </script>

        <script>
            (function () {
                const appUrl = <?php echo json_encode(config('app.url'), 15, 512) ?>;
                if (!appUrl) {
                    return;
                }
                try {
                    const target = new URL(appUrl);
                    const current = new URL(window.location.href);
                    const localHosts = new Set(['localhost', '127.0.0.1', '::1']);
                    // Avoid redirect loops and misconfigured APP_URL values (common on shared hosting).
                    if (localHosts.has(target.hostname)) {
                        return;
                    }
                    if (target.origin !== current.origin) {
                        window.location.replace(target.origin + current.pathname + current.search + current.hash);
                    }
                } catch (e) {
                    // noop
                }
            })();
        </script>

        <!-- Scripts -->
        <?php echo app('Illuminate\Foundation\Vite')->reactRefresh(); ?>
        <?php
            // Preload the current page chunk and all of its dependencies from the HTML instead of
            // discovering them one by one after app.js runs (a waterfall of 50-80 requests on the
            // inbox/dashboard). A missing manifest entry must never break the page itself.
            $viteEntries = ['resources/js/app.tsx'];
            $pageComponent = is_array($page ?? null) ? (string) ($page['component'] ?? '') : '';
            if ($pageComponent !== '' && preg_match('#^[A-Za-z0-9_/-]+$#', $pageComponent)
                && is_file(resource_path("js/Pages/{$pageComponent}.tsx"))) {
                $viteEntries[] = "resources/js/Pages/{$pageComponent}.tsx";
            }
            try {
                $viteTags = app(\Illuminate\Foundation\Vite::class)($viteEntries);
            } catch (\Throwable $e) {
                $viteTags = app(\Illuminate\Foundation\Vite::class)(['resources/js/app.tsx']);
            }
        ?>
        <?php echo e($viteTags); ?>

        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    var base = <?php echo json_encode(rtrim(request()->getBasePath(), '/'), 512) ?>;
                    var scope = (base || '') + '/';
                    var script = <?php echo json_encode(asset('sw.js'), 15, 512) ?>;
                    try {
                        var scriptUrl = new URL(script, window.location.href);
                        if (scriptUrl.origin !== window.location.origin || !scriptUrl.pathname.startsWith(scope)) {
                            return;
                        }
                        navigator.serviceWorker.register(scriptUrl.href, { scope: scope }).catch(function () {});
                    } catch (e) {}
                });
            }
        </script>
    </head>
    <body class="bg-ui-bg font-sans text-ui-text antialiased dark:bg-ui-dark-bg dark:text-ui-dark-text">
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } elseif (config('inertia.use_script_element_for_initial_page')) { ?><script data-page="app" type="application/json"><?php echo json_encode($page); ?></script><div id="app"></div><?php } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
    </body>
</html>
<?php /**PATH /workspace/oss/repo/resources/views/app.blade.php ENDPATH**/ ?>