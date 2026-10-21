<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['title']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?></title>
</head>
<body style="margin:0;background:#f4f7f5;font-family:Arial,Helvetica,sans-serif;color:#18231f;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7f5;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #dce5df;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background:#0f1f19;color:#ffffff;padding:22px 26px;border-bottom:4px solid <?php echo e($primary); ?>;">
                            <?php if($logoUrl): ?>
                                <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($brandName); ?>" style="display:block;max-width:150px;max-height:44px;margin-bottom:8px;">
                            <?php else: ?>
                                <div style="font-size:22px;font-weight:700;letter-spacing:.2px;"><?php echo e($brandName); ?></div>
                            <?php endif; ?>
                            <div style="margin-top:6px;color:#b8d5c4;font-size:13px;">Billing and invoices</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:26px;">
                            <?php echo e($slot); ?>

                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #e6ece8;padding:18px 26px;color:#6b7b73;font-size:12px;">
                            This email was sent by <?php echo e($brandName); ?>. Keep invoice and tax details for your records.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
<?php /**PATH /workspace/oss/repo/resources/views/components/emails/billing/layout.blade.php ENDPATH**/ ?>