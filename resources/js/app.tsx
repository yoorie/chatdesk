import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot, hydrateRoot } from 'react-dom/client';
import { route as ziggyRoute } from '../../vendor/tightenco/ziggy';
import { AppErrorBoundary, AppErrorFallback } from './Components/Errors/AppErrorBoundary';
import { AppProviders } from './Providers/AppProviders';
import { DEFAULT_PLATFORM_NAME, formatPageTitle } from './lib/branding';
import { isChunkLoadError, pageRecoveryUrl, reloadOnceForStaleChunks } from './lib/pageRecovery';

// Reuse Inertia's route configuration rather than emitting a second copy in Blade.
function readInitialPage(): any {
  try {
    return JSON.parse(document.getElementById('app')?.dataset.page || '{}');
  } catch {
    return {};
  }
}
const initialPage = readInitialPage();
(window as any).Ziggy = { ...initialPage.props?.ziggy, location: undefined };
(window as any).route = (name?: any, params?: any, absolute?: boolean) => {
  // Open-source edition: a link to a page that only exists in ChatDesk paid plans / Zyptos
  // resolves to '#' instead of crashing the page.
  if (typeof name === 'string' && (window as any).Ziggy?.routes && !(name in (window as any).Ziggy.routes)) {
    return '#';
  }
  return ziggyRoute(name, params, absolute, (window as any).Ziggy);
};

let pendingPageUrl: string | null = null;
let initialPageResolved = false;
router.on('before', (event) => {
  pendingPageUrl = event.detail.visit.method === 'get' ? event.detail.visit.url.href : null;
});
router.on('finish', () => {
  pendingPageUrl = null;
});

// Keep the Blade <meta name="csrf-token"> (read by the axios interceptor and by fetch callers)
// in sync with the server. Inertia visits never re-render the Blade root view, so after the
// session token is rotated (e.g. session()->regenerate() on login) the meta tag would be stale
// and the next POST would fail with 419.
router.on('navigate', (event) => {
  const token = (event.detail.page.props as any)?.csrf_token;
  if (typeof token !== 'string' || token === '') return;
  document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
  const axiosHeaders = (window as any).axios?.defaults?.headers?.common;
  if (axiosHeaders) axiosHeaders['X-CSRF-TOKEN'] = token;
});

createInertiaApp({
  title: (title) => {
    return formatPageTitle(
      title,
      (window as any).__brandingName || import.meta.env.VITE_APP_NAME || DEFAULT_PLATFORM_NAME
    );
  },
  resolve: async (name) => {
    try {
      const page = await resolvePageComponent(
        `./Pages/${name}.tsx`,
        import.meta.glob('./Pages/**/*.tsx')
      );
      initialPageResolved = true;
      return page;
    } catch (error) {
      if (!initialPageResolved) {
        // First paint: the page chunk (or one of its ~50-80 dependency chunks / CSS files) could
        // not be downloaded. Without this, createInertiaApp rejects before React mounts and the
        // user is left with a blank page. Reload once to fetch the current build.
        if (isChunkLoadError(error) && reloadOnceForStaleChunks()) {
          // If the browser does not navigate (blocked/aborted), fall back to the error card.
          return new Promise<never>((_, reject) => window.setTimeout(() => reject(error), 8000));
        }
        throw error;
      }
      const recoveryUrl = pageRecoveryUrl(error, pendingPageUrl, window.location.href);
      if (!recoveryUrl) throw error;
      // A fresh document uses the current manifest and preserves the requested destination.
      // Only a page navigation reaches this path; feature dialogs never trigger a reload.
      window.location.assign(recoveryUrl);
      return new Promise<never>(() => {});
    }
  },
  setup({ el, App, props }) {
    const pusherConfig = (props.initialPage?.props as any)?.pusherConfig;
    (window as any).__brandingName =
      (props.initialPage?.props as any)?.branding?.platform_name || DEFAULT_PLATFORM_NAME;

    const WrappedApp = (
      <AppErrorBoundary>
        <AppProviders pusherConfig={pusherConfig}>
          <App {...props} />
        </AppProviders>
      </AppErrorBoundary>
    );

    if (el.hasChildNodes()) {
      hydrateRoot(el, WrappedApp);
      return;
    }

    createRoot(el).render(WrappedApp);
  },
  progress: {
    color: '#4B5563',
  },
}).catch((error) => {
  // The initial page could not be resolved (and an automatic reload was already tried).
  // Show a recoverable card instead of an empty document.
  console.error('[App] Failed to start', error);
  const el = document.getElementById('app');
  if (!el) return;
  try {
    createRoot(el).render(<AppErrorFallback />);
  } catch {
    el.innerHTML =
      '<div style="padding:48px;text-align:center;font-family:sans-serif"><p>Something went wrong.</p><button onclick="location.reload()">Reload</button></div>';
  }
});
