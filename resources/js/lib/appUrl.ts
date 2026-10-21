/**
 * Prefix a root-relative path with the directory the app is installed in.
 * The base comes from <meta name="app-base"> (the request base path) or from
 * Inertia's shared Ziggy URL. Domain-root installs have an empty base.
 */
function appBase(hint?: string): string {
  const hinted = normalizeBase(hint);
  if (hinted) return hinted;

  if (typeof document !== 'undefined') {
    const meta = document.querySelector('meta[name="app-base"]')?.getAttribute('content') || '';
    const fromMeta = normalizeBase(meta);
    if (fromMeta) return fromMeta;
  }

  const ziggyUrl = typeof window !== 'undefined' ? (window as any).Ziggy?.url : '';
  return normalizeBase(ziggyUrl);
}

export function appUrl(path: string, hint?: string): string {
  if (!path) return appBase(hint) || '/';
  if (
    /^(https?:|mailto:|tel:)/i.test(path) ||
    path.startsWith('#') ||
    path.startsWith('data:') ||
    path.startsWith('blob:')
  ) {
    return path;
  }

  const hashIndex = path.indexOf('#');
  const hash = hashIndex >= 0 ? path.slice(hashIndex) : '';
  const withoutHash = hashIndex >= 0 ? path.slice(0, hashIndex) : path;
  const queryIndex = withoutHash.indexOf('?');
  const query = queryIndex >= 0 ? withoutHash.slice(queryIndex) : '';
  const pathname = queryIndex >= 0 ? withoutHash.slice(0, queryIndex) : withoutHash;
  const normalized = pathname.startsWith('/') ? pathname : `/${pathname}`;
  const base = appBase(hint);

  if (base && (normalized === base || normalized.startsWith(`${base}/`))) {
    return `${normalized}${query}${hash}`;
  }

  return `${base}${normalized}${query}${hash}`;
}

/** Subfolder installs prefix the path, so error pages cannot compare it with ===. */
export function pathIncludes(path: string, segment: string): boolean {
  const value = path.split('?')[0].split('#')[0];
  const needle = segment.startsWith('/') ? segment : `/${segment}`;
  return value === needle || value.endsWith(needle) || value.includes(`${needle}/`);
}

function normalizeBase(value?: string | null): string {
  if (!value) return '';
  let path = value.trim();
  if (!path) return '';
  if (/^https?:\/\//i.test(path)) {
    try {
      path = new URL(path).pathname;
    } catch {
      return '';
    }
  }
  path = path.replace(/\/+$/, '');
  return path === '/' || path === '' ? '' : path;
}
