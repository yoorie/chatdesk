import { appUrl } from '@/lib/appUrl';
import { cn } from '@/lib/utils';
import { Webhook } from 'lucide-react';

type ProviderLogoProps = {
  id?: string | null;
  name?: string | null;
  className?: string;
  imageClassName?: string;
};

const brandLogos: Record<string, { slug: string; color: string; label: string }> = {
  whatsapp: { slug: 'whatsapp', color: '00A548', label: 'WhatsApp' },
  'whatsapp.cloud': { slug: 'whatsapp', color: '00A548', label: 'WhatsApp' },
  'whatsapp-flows': { slug: 'whatsapp', color: '00A548', label: 'WhatsApp Flows' },
  'whatsapp-commerce': { slug: 'whatsapp', color: '00A548', label: 'WhatsApp Commerce' },
  'template-carousels': { slug: 'whatsapp', color: '00A548', label: 'Carousel Templates' },
  'meta-leads': { slug: 'meta', color: '0467DF', label: 'Meta' },
  'meta-catalog': { slug: 'meta', color: '0467DF', label: 'Meta Catalog' },
  'ctwa-ads': { slug: 'meta', color: '0467DF', label: 'Click-to-WhatsApp Ads' },
  meta: { slug: 'meta', color: '0467DF', label: 'Meta' },
  facebook: { slug: 'facebook', color: '1877F2', label: 'Facebook' },
  'google-sheets': { slug: 'googlesheets', color: '0F9D58', label: 'Google Sheets' },
  'google-calendar': { slug: 'googlecalendar', color: '4285F4', label: 'Google Calendar' },
  google: { slug: 'google', color: '4285F4', label: 'Google' },
  'razorpay-payments': { slug: 'razorpay', color: '0B72E7', label: 'Razorpay' },
  razorpay: { slug: 'razorpay', color: '0B72E7', label: 'Razorpay' },
  shopify: { slug: 'shopify', color: '7AB55C', label: 'Shopify' },
  woocommerce: { slug: 'woocommerce', color: '96588A', label: 'WooCommerce' },
  hubspot: { slug: 'hubspot', color: 'FF5C35', label: 'HubSpot' },
  salesforce: { slug: 'salesforce', color: '00A1E0', label: 'Salesforce' },
  zapier: { slug: 'zapier', color: 'FF4A00', label: 'Zapier' },
  make: { slug: 'make', color: '6D00CC', label: 'Make' },
  slack: { slug: 'slack', color: '4A154B', label: 'Slack' },
  openai: { slug: 'openai', color: '111827', label: 'OpenAI' },
  anthropic: { slug: 'anthropic', color: '111827', label: 'Anthropic' },
  gemini: { slug: 'googlegemini', color: '4285F4', label: 'Gemini' },
  'google-gemini': { slug: 'googlegemini', color: '4285F4', label: 'Gemini' },
  elevenlabs: { slug: 'elevenlabs', color: '111827', label: 'ElevenLabs' },
};

const aliases: Record<string, string> = {
  'whatsapp cloud api': 'whatsapp',
  whatsapp: 'whatsapp',
  'whatsapp flows': 'whatsapp-flows',
  'whatsapp commerce': 'whatsapp-commerce',
  'carousel templates': 'template-carousels',
  'meta leads': 'meta-leads',
  'meta catalog': 'meta-catalog',
  'click-to-whatsapp ads': 'ctwa-ads',
  'ctwa ads': 'ctwa-ads',
  meta: 'meta',
  facebook: 'facebook',
  'google sheets': 'google-sheets',
  'google calendar': 'google-calendar',
  razorpay: 'razorpay',
  'razorpay payments': 'razorpay-payments',
  shopify: 'shopify',
  woocommerce: 'woocommerce',
  hubspot: 'hubspot',
  salesforce: 'salesforce',
  zapier: 'zapier',
  make: 'make',
  slack: 'slack',
  openai: 'openai',
  anthropic: 'anthropic',
  gemini: 'gemini',
  elevenlabs: 'elevenlabs',
  webhooks: 'webhooks',
  'developer webhooks': 'developer-webhooks',
};

function normalizedProvider(id?: string | null, name?: string | null) {
  const rawId = String(id || '')
    .trim()
    .toLowerCase();
  if (rawId && (brandLogos[rawId] || rawId === 'workspace-ai')) return rawId;

  const rawName = String(name || '')
    .trim()
    .toLowerCase();
  return aliases[rawName] || rawId || rawName;
}

function logoUrl(slug: string, _color: string) {
  return appUrl(`/images/integrations/${slug}.svg`);
}

function MaskLogo({
  slug,
  color,
  label,
  className,
}: {
  slug: string;
  color: string;
  label: string;
  className?: string;
}) {
  const url = logoUrl(slug, color);

  return (
    <span
      aria-label={`${label} logo`}
      role="img"
      className={cn('block h-6 w-6', className)}
      style={{
        backgroundColor: `#${color}`,
        WebkitMask: `url(${url}) center / contain no-repeat`,
        mask: `url(${url}) center / contain no-repeat`,
      }}
    />
  );
}

function DeveloperWebhooksLogo({ className }: { className?: string }) {
  return (
    <Webhook
      role="img"
      aria-label="Webhooks icon"
      className={cn('h-6 w-6 text-violet-500 dark:text-violet-400', className)}
      strokeWidth={1.8}
    />
  );
}

export default function ProviderLogo({ id, name, className, imageClassName }: ProviderLogoProps) {
  const provider = normalizedProvider(id, name);

  if (provider === 'developer-webhooks' || provider === 'webhooks') {
    return (
      <span
        className={cn(
          'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-surface',
          className
        )}
        title="Developer Webhooks"
      >
        <DeveloperWebhooksLogo className={imageClassName} />
      </span>
    );
  }

  if (provider === 'workspace-ai') {
    return (
      <span
        className={cn(
          'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-surface',
          className
        )}
        title="OpenAI, Anthropic, and Gemini"
      >
        {['openai', 'anthropic', 'gemini'].map((key) => {
          const logo = brandLogos[key];
          return (
            <MaskLogo
              key={key}
              slug={logo.slug}
              color={logo.color}
              label={logo.label}
              className={cn('h-4 w-4', imageClassName)}
            />
          );
        })}
      </span>
    );
  }

  const logo = brandLogos[provider];
  if (!logo) {
    const initials = String(name || id || '?')
      .slice(0, 2)
      .toUpperCase();

    return (
      <span
        className={cn(
          'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-ui-green text-sm font-bold text-white',
          className
        )}
      >
        {initials}
      </span>
    );
  }

  return (
    <span
      className={cn(
        'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white shadow-sm dark:border-ui-dark-border dark:bg-ui-dark-surface',
        className
      )}
      title={logo.label}
    >
      <MaskLogo slug={logo.slug} color={logo.color} label={logo.label} className={imageClassName} />
    </span>
  );
}
