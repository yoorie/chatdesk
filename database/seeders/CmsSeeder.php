<?php

namespace Database\Seeders;

use App\Models\CmsPage;
use Illuminate\Database\Seeder;

/**
 * Neutral starter content for the website CMS. {app} is replaced with the
 * Branding app name when shown. Legal texts are templates: review them with
 * your own legal adviser before going live. Existing pages are never overwritten.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['privacy', 'Privacy Policy', '<p>This policy explains how {app} collects and uses personal data.</p><h2>Data we collect</h2><ul><li>Account details such as name, email and phone.</li><li>Workspace content such as contacts, messages and templates.</li><li>Billing records and technical logs.</li></ul><h2>How we use it</h2><p>To provide the service, keep it secure, process payments and offer support.</p><h2>Third parties</h2><p>We use providers such as Meta (WhatsApp Cloud API), payment gateways and email services only as needed to run {app}.</p><h2>Your rights</h2><p>You can request access, correction or deletion of your data by contacting us.</p><h2>Contact</h2><p>Questions? Use our <a href="/contact">contact page</a>.</p>'],
            ['terms', 'Terms of Service', '<p>By using {app} you agree to these terms.</p><h2>Your account</h2><p>Keep your login secure. You are responsible for activity in your workspace.</p><h2>Acceptable use</h2><p>Send messages only to people who opted in. Follow WhatsApp Business and Meta policies. No spam or illegal content.</p><h2>Billing</h2><p>Paid plans renew per your billing cycle. Meta message fees are separate.</p><h2>Termination</h2><p>We may suspend accounts that break these terms.</p><h2>Liability</h2><p>The service is provided "as is" to the extent allowed by law.</p>'],
            ['refund-policy', 'Refund Policy', '<p>We want you to be happy with {app}.</p><h2>Refunds</h2><p>Contact us within 7 days of a payment if something is wrong. Approved refunds go back to the original payment method.</p><h2>Not refundable</h2><ul><li>Meta / WhatsApp conversation charges.</li><li>Partially used billing periods, unless required by law.</li></ul>'],
            ['cookies', 'Cookie Policy', '<p>{app} uses essential cookies to keep you signed in and secure.</p><h2>Optional cookies</h2><p>Analytics cookies are used only if you accept them in the cookie banner.</p><h2>Control</h2><p>You can clear or block cookies in your browser settings.</p>'],
            ['about', 'About Us', '<p>{app} helps businesses talk to customers on WhatsApp: a shared inbox, campaigns, chatbots and analytics in one place.</p><h2>Our mission</h2><p>Make customer conversations simple, personal and measurable.</p>'],
            ['contact', 'Contact', '<p>Questions about {app}? Send us a message and we will reply soon.</p>'],
        ];

        foreach ($pages as $i => [$slug, $title, $body]) {
            CmsPage::firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'body' => $body,
                'status' => 'published',
                'is_system' => true,
                'menu_order' => $i,
            ]);
        }
    }
}
