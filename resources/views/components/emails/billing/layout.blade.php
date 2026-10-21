@props(['title'])
@php
    $emailBrand = $brand ?? app(\App\Services\BrandingService::class)->getAll();
    $brandName = $emailBrand['name'] ?? brand_name();
    $brandName = $emailBrand['platform_name'] ?? $brandName;
    $logoUrl = $emailBrand['logo_dark_url'] ?? $emailBrand['logo_url'] ?? null;
    if (is_string($logoUrl) && str_starts_with($logoUrl, '/')) {
        $logoUrl = url($logoUrl);
    }
    $primary = trim((string) ($emailBrand['primary_color'] ?? ''));
    if ($primary === '' || strtolower($primary) === '#3b82f6') {
        $primary = '#00A548';
    }
    $primaryText = $emailBrand['primary_text_color'] ?? '#0f1f19';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f4f7f5;font-family:Arial,Helvetica,sans-serif;color:#18231f;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f5;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #dce5df;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background:#0f1f19;color:#ffffff;padding:22px 26px;border-bottom:4px solid {{ $primary }};">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" style="display:block;max-width:150px;max-height:44px;margin-bottom:8px;">
                            @else
                                <div style="font-size:22px;font-weight:700;letter-spacing:.2px;">{{ $brandName }}</div>
                            @endif
                            <div style="margin-top:6px;color:#b8d5c4;font-size:13px;">Billing and invoices</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e6ece8;padding:18px 26px;color:#6b7b73;font-size:12px;">
                            This email was sent by {{ $brandName }}. Keep invoice and tax details for your records.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
