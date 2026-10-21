// White-label product name. Set from Admin > Settings > Branding (injected by app.blade.php).
declare global {
  interface Window {
    __APP_BRAND__?: string;
  }
}

export function brandName(): string {
  if (typeof window !== 'undefined' && window.__APP_BRAND__) return window.__APP_BRAND__;
  return 'ChatDesk';
}
