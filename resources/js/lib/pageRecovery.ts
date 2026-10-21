/**
 * Recovery for JavaScript/CSS chunks that can no longer be downloaded.
 *
 * Every page is a lazily loaded chunk with dozens of shared dependency chunks. After a deploy the
 * old fingerprinted files are pruned, and on a cold first visit a single failed request (stale
 * chunk 404, flaky network, CDN hiccup) used to reject the page import. Nothing caught that, so
 * React never mounted and the user saw a blank page until they reloaded by hand.
 */
const RELOAD_KEY = 'zyptos:chunk-reload-at';
const RELOAD_WINDOW_MS = 60_000;

const CHUNK_ERROR_PATTERN =
  /Failed to fetch dynamically imported module|error loading dynamically imported module|Importing a module script failed|Unable to preload CSS|Loading (?:CSS )?chunk \S+ failed|ChunkLoadError|is not a valid JavaScript MIME type/i;

export function isChunkLoadError(error: unknown): boolean {
  if (!error) return false;
  const message =
    typeof error === 'string'
      ? error
      : error instanceof Error
        ? `${error.name}: ${error.message}`
        : String((error as { message?: unknown }).message ?? '');
  return CHUNK_ERROR_PATTERN.test(message);
}

interface ReloadEnvironment {
  storage: Pick<Storage, 'getItem' | 'setItem'> | null;
  now: number;
  reload: () => void;
}

/**
 * Start a full reload at most once per minute so a permanently broken chunk can never cause a
 * reload loop. Returns true when a reload was started.
 */
export function reloadOnceForStaleChunks(env?: Partial<ReloadEnvironment>): boolean {
  let storage: ReloadEnvironment['storage'] = null;
  try {
    storage = env?.storage !== undefined ? env.storage : window.sessionStorage;
  } catch {
    storage = null;
  }
  const now = env?.now ?? Date.now();
  // Without sessionStorage we cannot remember that we already tried, so never risk a loop.
  if (!storage) return false;
  try {
    const last = Number(storage.getItem(RELOAD_KEY) || 0);
    if (last && now - last >= 0 && now - last < RELOAD_WINDOW_MS) return false;
    storage.setItem(RELOAD_KEY, String(now));
  } catch {
    return false;
  }
  (env?.reload ?? (() => window.location.reload()))();
  return true;
}

/** Recover only failed page-module downloads during an intentional GET navigation. */
export function pageRecoveryUrl(
  error: unknown,
  destination: string | null,
  currentUrl: string
): string | null {
  if (!destination || !(error instanceof Error)) return null;
  if (!isChunkLoadError(error)) return null;
  try {
    const current = new URL(currentUrl);
    const target = new URL(destination, current);
    return target.origin === current.origin && /^https?:$/.test(target.protocol)
      ? target.href
      : null;
  } catch {
    return null;
  }
}
