<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
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
        @endphp
        <script>window.__APP_BRAND__ = @json($platformName);</script>
        <style>
            :root {
                --brand-primary-rgb: {{ $brandingService->colorRgb($brandingService->getPrimaryColor(), '#00A548') }};
                --brand-secondary-rgb: {{ $brandingService->colorRgb($brandingService->getSecondaryColor(), '#053A35') }};
            }
        </style>
        @inertiaHead
        @if(!isset($__inertiaSsrResponse) || !$__inertiaSsrResponse || !str_contains($__inertiaSsrResponse->head, '<title'))
            <title inertia>{{ $seoPayload['title'] ?? $platformName }}</title>
        @endif

        @if($seoPayload)
            <meta name="description" content="{{ $seoPayload['description'] }}">
            @if(! empty($seoPayload['keywords']))
                <meta name="keywords" content="{{ $seoPayload['keywords'] }}">
            @endif
            <meta name="robots" content="{{ $seoPayload['robots'] }}">
            <link rel="canonical" href="{{ $seoPayload['canonical'] }}">
            <meta property="og:type" content="{{ $seoPayload['type'] ?? 'website' }}">
            <meta property="og:title" content="{{ $seoPayload['title'] }}">
            <meta property="og:description" content="{{ $seoPayload['description'] }}">
            <meta property="og:url" content="{{ $seoPayload['url'] }}">
            <meta property="og:site_name" content="{{ $seoPayload['site_name'] }}">
            <meta property="og:locale" content="{{ $seoPayload['locale'] }}">
            <meta property="og:image" content="{{ $seoPayload['image'] }}">
            <meta property="og:image:alt" content="{{ $seoPayload['site_name'] }} product preview">
            <meta name="twitter:card" content="{{ $seoPayload['twitter_card'] ?? 'summary_large_image' }}">
            <meta name="twitter:title" content="{{ $seoPayload['title'] }}">
            <meta name="twitter:description" content="{{ $seoPayload['description'] }}">
            <meta name="twitter:image" content="{{ $seoPayload['image'] }}">
            @foreach($seoPayload['schemas'] ?? [] as $index => $schema)
                <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
            @endforeach
        @endif

        @if($googleAnalyticsEnabled && $googleAnalyticsId !== '')
            <!-- Google tag (gtag.js) -->
            <script id="ga-script" async src="https://www.googletagmanager.com/gtag/js?id={{ $googleAnalyticsId }}"></script>
            <script id="ga-inline">
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', @json($googleAnalyticsId));
            </script>
        @endif

        <!-- Favicon (will be overridden by BrandingWrapper if custom favicon is set) -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <meta name="app-base" content="{{ rtrim(request()->getBasePath(), '/') }}">
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
                const appUrl = @json(config('app.url'));
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
        @viteReactRefresh
        @php
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
        @endphp
        {{ $viteTags }}
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    var base = @json(rtrim(request()->getBasePath(), '/'));
                    var scope = (base || '') + '/';
                    var script = @json(asset('sw.js'));
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
        @inertia
    </body>
</html>
