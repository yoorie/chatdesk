<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class SeoService
{
    public function __construct(
        protected BrandingService $branding,
        protected PlatformSettingsService $settings,
    ) {}

    public function page(string $key, array $overrides = []): array
    {
        $pages = $this->pages();
        $page = $pages[$key] ?? $pages['landing'];

        $cmsSeo = [];
        try {
            $cms = (array) (\App\Models\PlatformSetting::get('cms.seo', [])[$key] ?? []);
            $cmsSeo = array_filter([
                'title' => $cms['title'] ?? null,
                'description' => $cms['description'] ?? null,
                'image' => $cms['og_image'] ?? null,
            ]);
        } catch (\Throwable) {
        }
        $payload = array_replace_recursive($this->defaults(), $page, $cmsSeo, $overrides);
        $payload['url'] = $this->absoluteUrl($payload['path'] ?? '/');
        $payload['canonical'] = $payload['canonical'] ?? $payload['url'];
        $payload['image'] = $this->absoluteUrl($payload['image'] ?? '/images/marketing/og-default.png');
        $payload['site_name'] = $this->platformName();
        $payload['locale'] = str_replace('-', '_', app()->getLocale() ?: 'en');
        $payload['robots'] = $payload['robots'] ?? 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
        $payload['schemas'] = $this->schemasFor($key, $payload);

        unset($payload['path']);

        return $payload;
    }

    public function sitemapUrls(): Collection
    {
        return collect($this->pages())
            ->filter(fn (array $page) => ($page['sitemap'] ?? true) === true)
            ->map(fn (array $page) => [
                'loc' => $this->absoluteUrl($page['path']),
                'lastmod' => now()->toDateString(),
                'changefreq' => $page['changefreq'] ?? 'weekly',
                'priority' => $page['priority'] ?? '0.7',
            ])
            ->values();
    }

    public function pages(): array
    {
        return [
            'landing' => [
                'path' => '/',
                'title' => (brand_name().' | WhatsApp Marketing Platform for Sales, Support & Automation'),
                'description' => (brand_name().' helps teams run WhatsApp campaigns, shared inbox, Meta templates, AI agents, automations, payments, leads, and support from one workspace.'),
                'keywords' => 'WhatsApp marketing platform, WhatsApp Business API, WABA, WhatsApp automation, WhatsApp CRM, WhatsApp campaigns, Meta Leads',
                'type' => 'website',
                'priority' => '1.0',
                'changefreq' => 'daily',
            ],
            'pricing' => [
                'path' => '/pricing',
                'title' => (brand_name().' Pricing | WhatsApp Business Platform Plans'),
                'description' => ('Compare '.brand_name().' Starter, Pro, Business, and Enterprise plans for WhatsApp inbox, campaigns, automation, AI agents, billing, and integrations.'),
                'keywords' => (brand_name().' pricing, WhatsApp marketing pricing, WhatsApp automation plan, WABA platform pricing'),
                'priority' => '0.95',
                'changefreq' => 'daily',
            ],
            'about' => [
                'path' => '/about',
                'title' => ('About '.brand_name().' | WhatsApp Operations Platform'),
                'description' => ('Learn how '.brand_name().' helps businesses manage WhatsApp sales, support, automation, campaigns, payments, and Meta integrations.'),
                'priority' => '0.75',
            ],
            'contact' => [
                'path' => '/contact',
                'title' => ('Contact '.brand_name().' | Sales, Support & WhatsApp Setup'),
                'description' => ('Contact '.brand_name().' for WhatsApp Business API setup, pricing, demos, migration help, billing, and platform support.'),
                'priority' => '0.85',
            ],
            'faqs' => [
                'path' => '/faqs',
                'title' => (brand_name().' FAQs | WhatsApp API, Billing, Automations & Integrations'),
                'description' => ('Answers about '.brand_name().' workspaces, WhatsApp Business API setup, Meta templates, billing, AI agents, automations and integrations.'),
                'priority' => '0.8',
            ],
            'knowledgebase' => [
                'path' => '/knowledge-base',
                'title' => (brand_name().' Knowledge Base | Setup Guides & Best Practices'),
                'description' => ('Practical '.brand_name().' guides for WhatsApp setup, campaigns, automations, Meta Leads, Google integrations, billing, permissions, and security.'),
                'priority' => '0.8',
            ],
            'docs' => [
                'path' => '/docs',
                'title' => (brand_name().' Documentation | Workspace, Meta & Automation Guides'),
                'description' => ('Documentation for '.brand_name().' workspace setup, WhatsApp Cloud API, integrations, automations, payments, APIs, and operational safety.'),
                'priority' => '0.75',
            ],
            'roadmap' => [
                'path' => '/roadmap',
                'title' => (brand_name().' Roadmap | Customer-Focused WhatsApp Platform Updates'),
                'description' => ('See what '.brand_name().' has shipped, what is improving next, and planned customer-facing upgrades for WhatsApp operations.'),
                'priority' => '0.65',
            ],
            'help' => [
                'path' => '/help',
                'title' => (brand_name().' Help Center | WhatsApp Setup, Billing & Support'),
                'description' => ('Get help with '.brand_name().' WhatsApp setup, templates, campaigns, automations, payments, integrations, and troubleshooting.'),
                'priority' => '0.75',
            ],
            'security' => [
                'path' => '/security',
                'title' => (brand_name().' Security | Access Control, Audit Logs & Data Protection'),
                'description' => ('Review '.brand_name().' security practices for encryption, role-based access, audit logs, credentials, webhooks, sessions, and incident readiness.'),
                'priority' => '0.65',
            ],
            'privacy' => [
                'path' => '/privacy',
                'title' => (brand_name().' Privacy Policy'),
                'description' => ('Read how '.brand_name().' handles account, workspace, contact, billing, Meta, integration, support, and analytics data.'),
                'priority' => '0.5',
                'changefreq' => 'monthly',
            ],
            'terms' => [
                'path' => '/terms',
                'title' => (brand_name().' Terms of Service'),
                'description' => ('Terms for using '.brand_name().' workspaces, WhatsApp Business API tools, campaigns, AI, payments, billing, and integrations.'),
                'priority' => '0.5',
                'changefreq' => 'monthly',
            ],
            'refund' => [
                'path' => '/refund-policy',
                'title' => (brand_name().' Refund and Cancellation Policy'),
                'description' => ('Understand '.brand_name().' cancellations, renewals, payment proof review, invoice handling, and refund request process.'),
                'priority' => '0.45',
                'changefreq' => 'monthly',
            ],
            'acceptableUse' => [
                'path' => '/acceptable-use',
                'title' => (brand_name().' Acceptable Use Policy'),
                'description' => ('Messaging and automation rules for responsible '.brand_name().' use, consent, opt-outs, campaigns, templates, and Meta policy compliance.'),
                'priority' => '0.45',
                'changefreq' => 'monthly',
            ],
            'gdpr' => [
                'path' => '/gdpr',
                'title' => (brand_name().' GDPR Information'),
                'description' => ('GDPR information for '.brand_name().' customers, including data rights, processing, exports, deletion, and subprocessors.'),
                'priority' => '0.4',
                'changefreq' => 'monthly',
            ],
            'cookies' => [
                'path' => '/cookies',
                'title' => (brand_name().' Cookie Policy'),
                'description' => ('How '.brand_name().' uses essential cookies, session cookies, preferences, and optional analytics cookies.'),
                'priority' => '0.4',
                'changefreq' => 'monthly',
            ],
        ];
    }

    protected function defaults(): array
    {
        return [
            'title' => $this->platformName(),
            'description' => (brand_name().' is a WhatsApp marketing and operations platform for sales, support, campaigns, automations, AI agents, and integrations.'),
            'keywords' => (brand_name().', WhatsApp marketing, WhatsApp Business API'),
            'image' => '/images/marketing/og-default.png',
            'twitter_card' => 'summary_large_image',
            'type' => 'website',
        ];
    }

    protected function schemasFor(string $key, array $page): array
    {
        $schemas = [
            $this->organizationSchema(),
            $this->websiteSchema(),
            $this->softwareApplicationSchema(),
            $this->breadcrumbSchema($page),
        ];

        if ($key === 'pricing') {
            $schemas[] = $this->productSchema();
        }

        if ($key === 'faqs') {
            $schemas[] = $this->faqSchema();
        }

        return array_values(array_filter($schemas));
    }

    protected function organizationSchema(): array
    {
        $logo = $this->absoluteUrl($this->branding->getLogoUrl() ?: '/images/brand/logo-dark.png');
        $sameAs = array_values(array_filter([
            $this->settings->get('branding.linkedin_url'),
            $this->settings->get('branding.facebook_url'),
            $this->settings->get('branding.instagram_url'),
            $this->settings->get('branding.youtube_url'),
        ]));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $this->absoluteUrl('/#organization'),
            'name' => $this->platformName(),
            'url' => $this->absoluteUrl('/'),
            'logo' => $logo,
            'image' => $logo,
            'email' => $this->settings->get('branding.support_email') ?: $this->settings->get('general.support_email'),
            'telephone' => $this->settings->get('branding.support_phone') ?: null,
            'sameAs' => $sameAs ?: null,
        ]);
    }

    protected function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $this->absoluteUrl('/#website'),
            'name' => $this->platformName(),
            'url' => $this->absoluteUrl('/'),
            'publisher' => ['@id' => $this->absoluteUrl('/#organization')],
            'inLanguage' => 'en-IN',
        ];
    }

    protected function softwareApplicationSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            '@id' => $this->absoluteUrl('/#software'),
            'name' => $this->platformName(),
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web, Android, iOS',
            'url' => $this->absoluteUrl('/'),
            'description' => $this->defaults()['description'],
            'publisher' => ['@id' => $this->absoluteUrl('/#organization')],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'INR',
                'price' => $this->lowestPlanPrice(),
                'availability' => 'https://schema.org/InStock',
                'url' => $this->absoluteUrl('/pricing'),
            ],
        ];
    }

    protected function productSchema(): array
    {
        $offers = Plan::query()
            ->where('is_active', true)
            ->whereNotIn('key', ['free', 'enterprise'])
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $plan) => [
                '@type' => 'Offer',
                'name' => $plan->name,
                'description' => $plan->description,
                'priceCurrency' => strtoupper((string) ($plan->currency ?: $this->settings->get('payment.default_currency', 'INR'))),
                'price' => number_format(((int) $plan->price_monthly) / 100, 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
                'url' => $this->absoluteUrl('/pricing'),
            ])
            ->values()
            ->all();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $this->absoluteUrl('/pricing#plans'),
            'name' => (brand_name().' WhatsApp Business Platform'),
            'description' => 'Per-workspace WhatsApp inbox, campaigns, automations, AI agents, billing, and integrations.',
            'brand' => ['@id' => $this->absoluteUrl('/#organization')],
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'INR',
                'lowPrice' => $this->lowestPlanPrice(),
                'offerCount' => max(count($offers), 1),
                'offers' => $offers,
            ],
        ];
    }

    protected function faqSchema(): array
    {
        $items = [
            ['Do I need my own WhatsApp Business Account?', ('Yes. '.brand_name().' connects to your Meta WABA and guides you through phone registration, templates, webhooks, and team setup.')],
            ['How many WhatsApp numbers can one workspace connect?', 'As many as the plan includes. Starter includes one number. Higher plans include more on the same workspace. Use another workspace when a brand or client needs its own team and billing.'],
            ['Are Meta conversation charges included?', ('No. '.brand_name().' subscription fees and Meta conversation charges are separate. Meta charges depend on your WABA, country, and category.')],
            ['Can automations hand over to a human?', ('Yes. '.brand_name().' flows can assign a chat, pause the bot, show status in the inbox, and let agents take over manually.')],
            ['Can I use my own AI and Razorpay keys?', ('Yes. Workspace owners can add workspace credentials where available, while '.brand_name().' billing uses platform billing credentials.')],
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $item) => [
                '@type' => 'Question',
                'name' => $item[0],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item[1],
                ],
            ], $items),
        ];
    }

    protected function breadcrumbSchema(array $page): ?array
    {
        $url = $page['url'] ?? $this->absoluteUrl('/');
        if ($url === $this->absoluteUrl('/')) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => $this->absoluteUrl('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => Arr::get($page, 'title', $this->platformName()),
                    'item' => $url,
                ],
            ],
        ];
    }

    protected function lowestPlanPrice(): string
    {
        $price = Plan::query()
            ->where('is_active', true)
            ->whereNotIn('key', ['free', 'enterprise'])
            ->min('price_monthly');

        return number_format(((int) $price) / 100, 2, '.', '');
    }

    protected function platformName(): string
    {
        return $this->branding->getPlatformName() ?: (brand_name());
    }

    protected function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim((string) ($this->settings->get('general.platform_url') ?: config('app.url') ?: url('/')), '/');

        return $base.'/'.ltrim($path, '/');
    }
}
