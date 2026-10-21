<?php

namespace App\Services\Cms;

use App\Models\CmsPage;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Website CMS: landing sections, menus, announcement bar and SEO overrides
 * live in platform_settings (JSON); pages live in cms_pages. Public output
 * is cached until an admin saves. Text may use {app} for the brand name.
 */
class CmsService
{
    public const CACHE_KEY = 'cms.public.v1';

    public const SECTIONS = ['hero', 'integrations', 'features', 'steps', 'why', 'pricing', 'testimonials', 'faq', 'cta'];

    public const ICONS = ['message', 'zap', 'megaphone', 'chart', 'lock', 'trending', 'headphones', 'layers', 'users', 'inbox', 'bot', 'shield', 'globe', 'star'];

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function defaultLanding(): array
    {
        return [
            'order' => self::SECTIONS,
            'enabled' => array_fill_keys(self::SECTIONS, true),
            'hero' => [
                'eyebrow' => 'Conversations. Campaigns. Growth.',
                'title' => 'Turn WhatsApp into your growth engine.',
                'subtitle' => '{app} brings your team together to chat, automate, broadcast and build lasting customer relationships.',
                'cta_label' => 'Get started', 'cta_url' => '/register',
                'secondary_label' => 'Contact us', 'secondary_url' => '/contact',
            ],
            'integrations' => ['title' => 'Connected to the tools behind your business'],
            'features' => [
                'eyebrow' => 'Built for modern businesses',
                'title' => 'All your WhatsApp operations in one place.',
                'subtitle' => 'From support to campaigns, everything your team needs.',
                'items' => [
                    ['icon' => 'message', 'title' => 'Shared team inbox', 'text' => 'Manage all conversations together.'],
                    ['icon' => 'zap', 'title' => 'Automation & flows', 'text' => 'Save time with smart workflows.'],
                    ['icon' => 'megaphone', 'title' => 'Broadcast campaigns', 'text' => 'Send updates to the right audience.'],
                    ['icon' => 'chart', 'title' => 'Analytics & reports', 'text' => 'Track delivery and campaign results.'],
                ],
            ],
            'steps' => [
                'eyebrow' => 'A simple start',
                'title' => 'Set up once. Work better together.',
                'subtitle' => 'Connect your number, invite your team and start talking.',
                'items' => [
                    ['title' => 'Connect your number', 'text' => 'Link your WhatsApp Business Account.'],
                    ['title' => 'Bring your team', 'text' => 'Invite teammates and assign chats.'],
                    ['title' => 'Start the conversation', 'text' => 'Reply, automate and launch campaigns.'],
                ],
            ],
            'why' => [
                'title' => 'Why businesses choose {app}.',
                'items' => [
                    ['icon' => 'lock', 'title' => 'Roles & permissions', 'text' => 'Give each teammate the right access.'],
                    ['icon' => 'trending', 'title' => 'Room to grow', 'text' => 'Pick a plan that fits your stage.'],
                    ['icon' => 'headphones', 'title' => 'Helpful support', 'text' => 'Get help with setup and questions.'],
                    ['icon' => 'layers', 'title' => 'Separate workspaces', 'text' => 'Keep brands and clients organized.'],
                ],
            ],
            'pricing' => ['title' => 'Simple pricing'],
            'testimonials' => [
                'title' => 'Loved by growing teams',
                'items' => [
                    ['quote' => 'Our response time dropped from hours to minutes.', 'name' => 'Alex Morgan', 'role' => 'Support lead'],
                    ['quote' => 'Campaigns finally feel personal and easy to run.', 'name' => 'Sam Lee', 'role' => 'Marketing manager'],
                    ['quote' => 'The whole team works from one inbox now.', 'name' => 'Jordan Patel', 'role' => 'Founder'],
                ],
            ],
            'faq' => [
                'title' => 'Frequently asked questions',
                'items' => [
                    ['q' => 'Do I need a WhatsApp Business Account?', 'a' => 'Yes. You connect your own account through Meta in a few clicks.'],
                    ['q' => 'Are WhatsApp message fees included?', 'a' => 'No. Meta bills message fees separately from your {app} plan.'],
                    ['q' => 'Can I cancel anytime?', 'a' => 'Yes. Plans can be changed or cancelled from your billing page.'],
                ],
            ],
            'cta' => [
                'title' => 'Ready to grow with WhatsApp?',
                'subtitle' => 'Start in minutes. No credit card needed for the trial.',
                'button_label' => 'Get started', 'button_url' => '/register',
            ],
        ];
    }

    public static function defaultMenus(): array
    {
        return [
            'header' => [
                ['label' => 'Features', 'url' => '/#features', 'new_tab' => false],
                ['label' => 'Pricing', 'url' => '/pricing', 'new_tab' => false],
                ['label' => 'Contact', 'url' => '/contact', 'new_tab' => false],
            ],
            'footer' => [
                ['label' => 'About', 'url' => '/about', 'new_tab' => false],
                ['label' => 'Pricing', 'url' => '/pricing', 'new_tab' => false],
                ['label' => 'Contact', 'url' => '/contact', 'new_tab' => false],
            ],
            'footer_legal' => [
                ['label' => 'Privacy', 'url' => '/privacy', 'new_tab' => false],
                ['label' => 'Terms', 'url' => '/terms', 'new_tab' => false],
                ['label' => 'Refunds', 'url' => '/refund-policy', 'new_tab' => false],
                ['label' => 'Cookies', 'url' => '/cookies', 'new_tab' => false],
            ],
        ];
    }

    public static function defaultAnnouncement(): array
    {
        return ['enabled' => false, 'text' => 'New: AI replies are here.', 'link_label' => 'Learn more', 'link_url' => '/pricing'];
    }

    public function landing(): array
    {
        return array_replace_recursive(self::defaultLanding(), (array) PlatformSetting::get('cms.landing', []));
    }

    public function menus(): array
    {
        return array_replace(self::defaultMenus(), (array) PlatformSetting::get('cms.menus', []));
    }

    public function announcement(): array
    {
        return array_replace(self::defaultAnnouncement(), (array) PlatformSetting::get('cms.announcement', []));
    }

    public function seo(): array
    {
        return (array) PlatformSetting::get('cms.seo', []);
    }

    /** Everything the public site needs, cached, {app} replaced. */
    public function publicData(): array
    {
        if (! $this->ready()) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            $menus = $this->menus();
            $pages = CmsPage::published()->orderBy('menu_order')->orderBy('title')->get();
            foreach ($pages as $p) {
                $item = ['label' => $p->title, 'url' => $p->url(), 'new_tab' => false];
                if ($p->show_in_header && ! collect($menus['header'])->contains('url', $item['url'])) {
                    $menus['header'][] = $item;
                }
                if ($p->show_in_footer && ! collect($menus['footer'])->contains('url', $item['url']) && ! collect($menus['footer_legal'])->contains('url', $item['url'])) {
                    $menus['footer'][] = $item;
                }
            }

            return $this->brand([
                'landing' => $this->landing(),
                'menus' => $menus,
                'announcement' => $this->announcement(),
                'seo' => $this->seo(),
            ]);
        });
    }

    public function brand(array $data): array
    {
        $name = function_exists('brand_name') ? brand_name() : config('app.name');
        array_walk_recursive($data, function (&$v) use ($name) {
            if (is_string($v)) {
                $v = str_replace('{app}', $name, $v);
            }
        });

        return $data;
    }

    public function ready(): bool
    {
        try {
            return Schema::hasTable('cms_pages');
        } catch (\Throwable) {
            return false;
        }
    }

    // ---------- Sanitizing admin input ----------

    public function cleanLanding(array $in): array
    {
        $d = self::defaultLanding();
        $t = fn ($v, $max = 300) => HtmlSanitizer::text($v, $max);
        $u = fn ($v) => HtmlSanitizer::url($v);
        $out = [];
        $order = array_values(array_intersect((array) ($in['order'] ?? []), self::SECTIONS));
        $out['order'] = array_values(array_unique(array_merge($order, self::SECTIONS)));
        foreach (self::SECTIONS as $s) {
            $out['enabled'][$s] = (bool) ($in['enabled'][$s] ?? false);
        }
        $h = (array) ($in['hero'] ?? []);
        $out['hero'] = [
            'eyebrow' => $t($h['eyebrow'] ?? '', 120), 'title' => $t($h['title'] ?? '', 160), 'subtitle' => $t($h['subtitle'] ?? '', 400),
            'cta_label' => $t($h['cta_label'] ?? '', 40), 'cta_url' => $u($h['cta_url'] ?? ''),
            'secondary_label' => $t($h['secondary_label'] ?? '', 40), 'secondary_url' => $u($h['secondary_url'] ?? ''),
        ];
        $out['integrations'] = ['title' => $t($in['integrations']['title'] ?? '', 160)];
        foreach (['features', 'why'] as $s) {
            $sec = (array) ($in[$s] ?? []);
            $out[$s] = ['eyebrow' => $t($sec['eyebrow'] ?? '', 120), 'title' => $t($sec['title'] ?? '', 160), 'subtitle' => $t($sec['subtitle'] ?? '', 400), 'items' => []];
            foreach (array_slice((array) ($sec['items'] ?? []), 0, 12) as $it) {
                $out[$s]['items'][] = ['icon' => in_array($it['icon'] ?? '', self::ICONS, true) ? $it['icon'] : 'star', 'title' => $t($it['title'] ?? '', 80), 'text' => $t($it['text'] ?? '', 240)];
            }
        }
        $sec = (array) ($in['steps'] ?? []);
        $out['steps'] = ['eyebrow' => $t($sec['eyebrow'] ?? '', 120), 'title' => $t($sec['title'] ?? '', 160), 'subtitle' => $t($sec['subtitle'] ?? '', 400), 'items' => []];
        foreach (array_slice((array) ($sec['items'] ?? []), 0, 6) as $it) {
            $out['steps']['items'][] = ['title' => $t($it['title'] ?? '', 80), 'text' => $t($it['text'] ?? '', 240)];
        }
        $out['pricing'] = ['title' => $t($in['pricing']['title'] ?? $d['pricing']['title'], 160)];
        $sec = (array) ($in['testimonials'] ?? []);
        $out['testimonials'] = ['title' => $t($sec['title'] ?? '', 160), 'items' => []];
        foreach (array_slice((array) ($sec['items'] ?? []), 0, 12) as $it) {
            $out['testimonials']['items'][] = ['quote' => $t($it['quote'] ?? '', 400), 'name' => $t($it['name'] ?? '', 80), 'role' => $t($it['role'] ?? '', 80)];
        }
        $sec = (array) ($in['faq'] ?? []);
        $out['faq'] = ['title' => $t($sec['title'] ?? '', 160), 'items' => []];
        foreach (array_slice((array) ($sec['items'] ?? []), 0, 30) as $it) {
            $out['faq']['items'][] = ['q' => $t($it['q'] ?? '', 200), 'a' => $t($it['a'] ?? '', 1000)];
        }
        $c = (array) ($in['cta'] ?? []);
        $out['cta'] = ['title' => $t($c['title'] ?? '', 160), 'subtitle' => $t($c['subtitle'] ?? '', 300), 'button_label' => $t($c['button_label'] ?? '', 40), 'button_url' => $u($c['button_url'] ?? '')];

        return $out;
    }

    public function cleanMenus(array $in): array
    {
        $out = [];
        foreach (['header', 'footer', 'footer_legal'] as $m) {
            $out[$m] = [];
            foreach (array_slice((array) ($in[$m] ?? []), 0, 20) as $it) {
                $label = HtmlSanitizer::text($it['label'] ?? '', 40);
                $url = HtmlSanitizer::url($it['url'] ?? '');
                if ($label !== '' && $url !== '' && $url !== '#') {
                    $out[$m][] = ['label' => $label, 'url' => $url, 'new_tab' => (bool) ($it['new_tab'] ?? false)];
                }
            }
        }

        return $out;
    }

    public function cleanAnnouncement(array $in): array
    {
        return [
            'enabled' => (bool) ($in['enabled'] ?? false),
            'text' => HtmlSanitizer::text($in['text'] ?? '', 200),
            'link_label' => HtmlSanitizer::text($in['link_label'] ?? '', 40),
            'link_url' => HtmlSanitizer::url($in['link_url'] ?? ''),
        ];
    }

    public function cleanSeo(array $in): array
    {
        $out = [];
        foreach (['landing', 'pricing'] as $p) {
            $out[$p] = [
                'title' => HtmlSanitizer::text($in[$p]['title'] ?? '', 120),
                'description' => HtmlSanitizer::text($in[$p]['description'] ?? '', 300),
                'og_image' => HtmlSanitizer::url($in[$p]['og_image'] ?? ''),
            ];
        }

        return $out;
    }
}
