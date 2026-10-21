<?php

namespace App\Modules\WhatsApp\Support;

class PreapprovedTemplateLibrary
{
    public static function all(): array
    {
        return [
            [
                'id' => 'order_update_basic',
                'name' => 'order_update_basic',
                'title' => 'Order update',
                'category' => 'UTILITY',
                'language' => 'en_US',
                'header_type' => 'TEXT',
                'header_text' => 'Order update',
                'body_text' => 'Hi {{1}}, your order {{2}} is now {{3}}. Track it here: {{4}}',
                'body_examples' => ['Prashant', 'ORD-1042', 'out for delivery', 'https://example.com/track/ORD-1042'],
                'footer_text' => 'Thank you for shopping with us.',
                'buttons' => [
                    ['type' => 'URL', 'text' => 'Track order', 'url' => 'https://example.com/track/{{1}}', 'url_example' => 'https://example.com/track/ORD-1042'],
                ],
                'use_case' => 'Transactional order status notifications.',
            ],
            [
                'id' => 'appointment_reminder',
                'name' => 'appointment_reminder',
                'title' => 'Appointment reminder',
                'category' => 'UTILITY',
                'language' => 'en_US',
                'header_type' => 'TEXT',
                'header_text' => 'Appointment reminder',
                'body_text' => 'Hi {{1}}, this is a reminder for your appointment with {{2}} on {{3}} at {{4}}.',
                'body_examples' => ['Aditi', (brand_name().' Clinic'), '18 May', '4:30 PM'],
                'footer_text' => 'Reply if you need help.',
                'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'Confirm'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Reschedule'],
                ],
                'use_case' => 'Healthcare, salon, consultation, and service reminders.',
            ],
            [
                'id' => 'payment_reminder',
                'name' => 'payment_reminder',
                'title' => 'Payment reminder',
                'category' => 'UTILITY',
                'language' => 'en_US',
                'header_type' => 'NONE',
                'body_text' => 'Hi {{1}}, your payment of {{2}} for {{3}} is due on {{4}}. Please complete it to avoid interruption.',
                'body_examples' => ['Prashant', 'INR 1,499', (brand_name().' Pro'), '20 May'],
                'footer_text' => 'Ignore this if already paid.',
                'buttons' => [
                    ['type' => 'URL', 'text' => 'Pay now', 'url' => 'https://example.com/pay/{{1}}', 'url_example' => 'https://example.com/pay/inv_1001'],
                ],
                'use_case' => 'Invoice, renewal, subscription, and collection reminders.',
            ],
            [
                'id' => 'welcome_offer',
                'name' => 'welcome_offer',
                'title' => 'Welcome offer',
                'category' => 'MARKETING',
                'language' => 'en_US',
                'header_type' => 'TEXT',
                'header_text' => 'Welcome to {{1}}',
                'body_text' => 'Hi {{1}}, welcome to {{2}}. Use code {{3}} to get {{4}} off your first purchase.',
                'body_examples' => ['Prashant', (brand_name().' Store'), 'WELCOME10', '10%'],
                'footer_text' => 'Offer valid for a limited time.',
                'buttons' => [
                    ['type' => 'URL', 'text' => 'Shop now', 'url' => 'https://example.com/offers'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Need help'],
                ],
                'use_case' => 'Opt-in welcome campaigns and first-purchase offers.',
            ],
            [
                'id' => 'back_in_stock',
                'name' => 'back_in_stock',
                'title' => 'Back in stock',
                'category' => 'MARKETING',
                'language' => 'en_US',
                'header_type' => 'TEXT',
                'header_text' => 'Back in stock',
                'body_text' => 'Good news, {{1}}. {{2}} is back in stock. Order now before it sells out again.',
                'body_examples' => ['Prashant', 'Green cotton shirt'],
                'footer_text' => 'You are receiving this because you asked for stock alerts.',
                'buttons' => [
                    ['type' => 'URL', 'text' => 'View product', 'url' => 'https://example.com/products/{{1}}', 'url_example' => 'https://example.com/products/green-shirt'],
                ],
                'use_case' => 'Retail restock alerts for opted-in customers.',
            ],
            [
                'id' => 'login_otp',
                'name' => 'login_otp',
                'title' => 'Login OTP',
                'category' => 'AUTHENTICATION',
                'language' => 'en_US',
                'header_type' => 'NONE',
                'body_text' => '{{1}} is your verification code. For your security, do not share this code.',
                'body_examples' => ['123456'],
                'footer_text' => 'This code expires shortly.',
                'buttons' => [
                    ['type' => 'QUICK_REPLY', 'text' => 'Copy code'],
                ],
                'use_case' => 'One-time password and secure login verification.',
            ],
        ];
    }

    public static function find(?string $id): ?array
    {
        if (! $id) {
            return null;
        }

        foreach (static::all() as $template) {
            if ($template['id'] === $id) {
                return $template;
            }
        }

        return null;
    }
}
