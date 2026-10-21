import { isChunkLoadError, reloadOnceForStaleChunks } from '@/lib/pageRecovery';
import { Component, ErrorInfo, ReactNode } from 'react';

/**
 * Friendly fallback shown instead of a blank screen. Deliberately self-contained (no providers,
 * no shared UI kit) so it still renders when the rest of the app failed to load.
 */
export function AppErrorFallback({ reloading = false }: { reloading?: boolean }) {
  return (
    <div
      role="alert"
      className="flex min-h-[100dvh] items-center justify-center bg-gray-50 p-6 dark:bg-slate-950"
    >
      <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <h1 className="text-lg font-semibold text-gray-900 dark:text-white">
          {reloading ? 'Loading the latest version…' : 'Something went wrong'}
        </h1>
        <p className="mt-2 text-sm text-gray-600 dark:text-slate-300">
          {reloading
            ? 'We just updated the app. This page will refresh in a moment.'
            : 'This page did not load properly. Reloading usually fixes it.'}
        </p>
        {!reloading && (
          <div className="mt-6 flex justify-center gap-3">
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
            >
              Reload
            </button>
            <a
              href={typeof route !== 'undefined' ? route('landing') : '/'}
              className="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              Go to home
            </a>
          </div>
        )}
      </div>
    </div>
  );
}

interface State {
  error: unknown;
  reloading: boolean;
}

/** Top-level boundary: an error while rendering any page must never leave the screen blank. */
export class AppErrorBoundary extends Component<{ children: ReactNode }, State> {
  state: State = { error: null, reloading: false };

  static getDerivedStateFromError(error: unknown): Partial<State> {
    return { error: error ?? new Error('Unknown render error') };
  }

  componentDidCatch(error: unknown, info: ErrorInfo) {
    // A lazily loaded chunk that 404s after a deploy: one automatic reload picks up the new build.
    if (isChunkLoadError(error) && reloadOnceForStaleChunks()) {
      this.setState({ reloading: true });
      // If the reload does not happen, offer the Reload button instead of waiting forever.
      window.setTimeout(() => this.setState({ reloading: false }), 8000);
      return;
    }
    console.error('[App] Page failed to render', error, info.componentStack);
  }

  render() {
    if (this.state.error) return <AppErrorFallback reloading={this.state.reloading} />;
    return this.props.children;
  }
}
