import { appUrl } from '@/lib/appUrl';
import { brandName } from '../../lib/brand';
import AppLogo from '@/Components/Branding/AppLogo';
import { cn } from '@/lib/utils';
import { useTheme } from '@/Providers/ThemeProvider';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type BrandVariant = 'auto' | 'light' | 'dark';

interface BrandLogoProps {
  variant?: BrandVariant;
  compact?: boolean;
  className?: string;
  imageClassName?: string;
  fallbackTextClassName?: string;
}

export default function BrandLogo({
  variant = 'auto',
  compact = false,
  className,
  imageClassName,
  fallbackTextClassName = 'text-ui-text dark:text-ui-dark-text',
}: BrandLogoProps) {
  const page = usePage();
  const { branding } = page.props as any;
  const { resolvedTheme } = useTheme();
  const mode = variant === 'auto' ? resolvedTheme : variant;
  const platformName = branding?.platform_name || `${brandName()}`;
  const [failedLogoUrls, setFailedLogoUrls] = useState<string[]>([]);
  const assetBase = (page.props as any)?.ziggy?.url as string | undefined;
  const bundledLogoUrl = appUrl(
    compact
      ? mode === 'dark'
        ? '/images/brand/icon-white.webp'
        : '/images/brand/icon-dark.webp'
      : mode === 'dark'
        ? '/images/brand/logo-white.webp'
        : '/images/brand/logo-dark.webp',
    assetBase
  );

  const logoUrl = compact
    ? mode === 'dark'
      ? branding?.sidebar_icon_dark_url || branding?.logo_dark_url || bundledLogoUrl
      : branding?.sidebar_icon_url || branding?.logo_url || bundledLogoUrl
    : mode === 'dark'
      ? branding?.logo_dark_url || bundledLogoUrl
      : branding?.logo_url || bundledLogoUrl;
  const failedSet = new Set(failedLogoUrls);
  const effectiveLogoUrl = failedSet.has(logoUrl) ? bundledLogoUrl : logoUrl;
  const showImage = Boolean(effectiveLogoUrl && !failedSet.has(effectiveLogoUrl));

  useEffect(() => {
    setFailedLogoUrls([]);
  }, [logoUrl]);

  return (
    <span className={cn('inline-flex min-w-0 items-center gap-2', className)}>
      {showImage ? (
        <img
          src={effectiveLogoUrl}
          alt={platformName}
          width={compact ? 32 : 94}
          height={32}
          className={cn(
            compact ? 'h-8 w-8 object-contain' : 'h-8 w-auto max-w-full object-contain',
            imageClassName
          )}
          loading="eager"
          decoding="async"
          onError={() =>
            setFailedLogoUrls((current) =>
              effectiveLogoUrl && !current.includes(effectiveLogoUrl)
                ? [...current, effectiveLogoUrl]
                : current
            )
          }
        />
      ) : (
        <AppLogo withText={!compact} textClassName={fallbackTextClassName} />
      )}
    </span>
  );
}
