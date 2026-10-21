@php
    $brand = app(\App\Services\BrandingService::class)->getAll();
    $brandName = $brand['platform_name'] ?? $brand['name'] ?? brand_name();
    $logoUrl = $brand['logo_dark_url'] ?? $brand['logo_url'] ?? null;
    if (is_string($logoUrl) && str_starts_with($logoUrl, '/')) {
        $logoUrl = url($logoUrl);
    }
    $primary = trim((string) ($brand['primary_color'] ?? ''));
    if ($primary === '' || strtolower($primary) === '#3b82f6') {
        $primary = '#00A548';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $campaign->subject }}</title>
</head>
<body style="margin:0;background:#f4f7f5;font-family:Arial,Helvetica,sans-serif;color:#17231f;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f5;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #dce5df;border-radius:10px;overflow:hidden;">
                <tr>
                    <td style="background:#0f1f19;color:#ffffff;padding:22px 26px;border-bottom:4px solid {{ $primary }};">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $brandName }}" style="display:block;max-width:150px;max-height:44px;margin-bottom:8px;">
                        @else
                            <div style="font-size:22px;font-weight:700;">{{ $brandName }}</div>
                        @endif
                        <div style="margin-top:6px;color:#b8d5c4;font-size:13px;">Updates and offers</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:26px;">
                        <p style="margin:0 0 14px;color:#53645c;font-size:14px;">Hi {{ $recipient->name ?: 'there' }},</p>
                        <div style="font-size:15px;line-height:1.7;color:#17231f;">
                            {!! nl2br(e($campaign->body)) !!}
                        </div>
                        @if($campaign->offer_code)
                            <div style="margin-top:18px;padding:12px 14px;border-radius:8px;background:#effaf3;border:1px solid #c7efd7;color:#0f5132;">
                                <strong>Offer code:</strong> <span style="font-family:monospace;">{{ $campaign->offer_code }}</span>
                            </div>
                        @endif
                        @if($campaign->cta_label && $campaign->cta_url)
                            <p style="margin:22px 0 0;">
                                <a href="{{ $campaign->cta_url }}" style="display:inline-block;background:{{ $primary }};color:#0f1f19;text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">{{ $campaign->cta_label }}</a>
                            </p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="border-top:1px solid #e6ece8;padding:18px 26px;color:#6b7b73;font-size:12px;line-height:1.5;">
                        Sent by {{ $brandName }} platform admin. You are receiving this because you have an account or workspace relationship.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
