import { GlobalFlashHandler } from '@/Components/Notifications/GlobalFlashHandler';
import { DEFAULT_PLATFORM_NAME, formatPageTitle, hexToRgb } from '@/lib/branding';
import { useTheme } from '@/Providers/ThemeProvider';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * BrandingWrapper Component
 *
 * Wraps the app and applies branding (favicon, colors) dynamically.
 * This component is used inside the Inertia app tree so it can use usePage().
 */
export function BrandingWrapper({ children }: { children: React.ReactNode }) {
  const { branding } = usePage().props as any;
  const { resolvedTheme } = useTheme();
  const previousNameRef = useRef(DEFAULT_PLATFORM_NAME);

  useEffect(() => {
    if (!branding) {
      return;
    }

    // Store branding name globally for title callback access
    if (branding.platform_name) {
      (window as any).__brandingName = branding.platform_name;
    }

    if (branding.platform_name) {
      const previousName = previousNameRef.current;
      const title = document.title.endsWith(` - ${previousName}`)
        ? document.title.slice(0, -(previousName.length + 3))
        : document.title === 'Laravel'
          ? ''
          : document.title;
      document.title = formatPageTitle(title, branding.platform_name);
      previousNameRef.current = branding.platform_name;
    }

    // Update favicon
    const faviconUrl =
      resolvedTheme === 'dark'
        ? branding.favicon_dark_url || branding.favicon_url
        : branding.favicon_url || branding.favicon_dark_url;

    if (faviconUrl) {
      // Remove existing favicon links
      const existingLinks = document.querySelectorAll("link[rel*='icon']");
      existingLinks.forEach((link) => link.remove());

      // Add new favicon
      const link = document.createElement('link');
      link.rel = 'icon';
      link.type = faviconUrl.endsWith('.ico') ? 'image/x-icon' : 'image/png';
      link.href = faviconUrl;
      document.getElementsByTagName('head')[0].appendChild(link);
    }

    // Apply CSS variables for branding colors
    if (branding.primary_color) {
      document.documentElement.style.setProperty('--brand-primary', branding.primary_color);
      const rgb = hexToRgb(branding.primary_color);
      if (rgb) document.documentElement.style.setProperty('--brand-primary-rgb', rgb);
    }
    if (branding.secondary_color) {
      document.documentElement.style.setProperty('--brand-secondary', branding.secondary_color);
      const rgb = hexToRgb(branding.secondary_color);
      if (rgb) document.documentElement.style.setProperty('--brand-secondary-rgb', rgb);
    }
  }, [branding, resolvedTheme]);

  return (
    <>
      {children}
      <GlobalFlashHandler />
    </>
  );
}
