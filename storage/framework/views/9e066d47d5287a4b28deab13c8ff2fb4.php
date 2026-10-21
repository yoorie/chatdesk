<?php
    $money = fn ($amount) => $invoice['currency'].' '.number_format(((int) $amount) / 100, 2);
    $address = function ($profile) {
        $parts = collect([
            $profile['address_line1'] ?? null,
            $profile['address_line2'] ?? null,
            $profile['city'] ?? null,
            $profile['state'] ?? null,
            $profile['postal_code'] ?? null,
        ])->filter()->values();

        if ($parts->isEmpty()) {
            return '-';
        }

        $country = $profile['country'] ?? null;

        return $parts->push($country)->filter()->implode(', ');
    };
    $instructions = $invoice['payment_instructions'] ?? [];
    $configuredPaymentRows = collect([
        ['Account', $instructions['account_name'] ?? null],
        ['A/C No.', $instructions['account_number'] ?? null],
        ['IFSC', $instructions['ifsc'] ?? null],
        ['Bank', $instructions['bank_name'] ?? null],
        ['UPI', $instructions['upi_id'] ?? null],
        ['Payee', $instructions['upi_payee_name'] ?? null],
    ])->filter(fn ($row) => filled($row[1] ?? null))->values();
    $splitRate = number_format(((float) $invoice['tax_rate']) / 2, 2);
    $fullRate = number_format((float) $invoice['tax_rate'], 2);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo e($invoice['number']); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #17231f; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.32; }
        .page { padding: 22px 30px 24px; }
        .sheet { background: #fff; border: 1px solid #e4ebe7; border-radius: 10px; padding: 20px 20px 16px; }
        .header { border-bottom: 1px solid #e2ebe6; padding-bottom: 12px; }
        .brand { float: left; width: 52%; }
        .brand-logo { max-width: 132px; max-height: 40px; margin-bottom: 5px; }
        .brand-text { color: <?php echo e($brand['primary_color']); ?>; font-size: 23px; font-weight: 700; }
        .invoice-title { float: right; width: 42%; text-align: right; }
        h1 { margin: 0; color: #111c18; font-size: 23px; letter-spacing: .4px; }
        .accent { width: 46px; height: 3px; margin: 7px 0 0 auto; border-radius: 999px; background: <?php echo e($brand['primary_color']); ?>; }
        .status { display: inline-block; margin-top: 7px; padding: 4px 8px; border-radius: 12px; background: #edf7f1; color: #166534; border: 1px solid #cce8d6; font-weight: 700; font-size: 9px; }
        .clear { clear: both; }
        .muted { color: #68766f; }
        .block { margin-top: 14px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { width: 50%; vertical-align: top; padding: 0 12px 0 0; }
        .label { color: #68766f; font-size: 8.5px; font-weight: 700; letter-spacing: .45px; text-transform: uppercase; }
        .name { margin-top: 3px; font-size: 12.5px; font-weight: 700; }
        .box { margin-top: 5px; min-height: 48px; padding: 8px; border: 1px solid #dfe8e2; border-radius: 7px; background: #fbfdfc; }
        .items { width: 100%; margin-top: 14px; border-collapse: collapse; }
        .items th { padding: 7px 8px; background: #f3f7f5; color: #34433c; border-bottom: 1px solid #d7e5dc; font-size: 8.5px; letter-spacing: .45px; text-align: left; text-transform: uppercase; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #edf2ef; }
        .right { text-align: right; }
        .total-row td { background: #f5faf7; border-top: 2px solid <?php echo e($brand['primary_color']); ?>; font-size: 12.5px; font-weight: 700; }
        .discount td { color: #0b8f47; }
        .meta { width: 100%; margin-top: 10px; border-collapse: collapse; }
        .meta td { padding: 3px 0; }
        .payment-box { margin-top: 10px; padding: 8px 10px; border: 1px solid #d9e7df; border-left: 3px solid <?php echo e($brand['primary_color']); ?>; border-radius: 7px; background: #fbfdfc; }
        .payment-title { float: left; width: 24%; }
        .payment-grid { float: right; width: 74%; border-collapse: collapse; }
        .payment-grid td { width: 50%; padding: 1px 8px 3px 0; vertical-align: top; }
        .payment-note { clear: both; padding-top: 4px; font-size: 9.5px; }
        .empty-payment { float: right; width: 74%; color: #68766f; padding-top: 3px; }
        .footer { margin: 18px 30px 0; color: #68766f; font-size: 9px; border-top: 1px solid #e5ece8; padding-top: 7px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="sheet">
            <div class="header">
                <div class="brand">
                    <?php if($brand['logo_data_uri']): ?>
                        <img class="brand-logo" src="<?php echo e($brand['logo_data_uri']); ?>" alt="<?php echo e($brand['name']); ?>">
                    <?php else: ?>
                        <div class="brand-text"><?php echo e($brand['name']); ?></div>
                    <?php endif; ?>
                    <div class="muted">Billing and tax invoice</div>
                </div>
                <div class="invoice-title">
                    <h1>TAX INVOICE</h1>
                    <div class="accent"></div>
                    <div class="muted"><?php echo e($invoice['number']); ?></div>
                    <div class="status"><?php echo e($invoice['status']); ?></div>
                </div>
                <div class="clear"></div>
            </div>

            <table class="meta">
                <tr>
                    <td><span class="label">Issued</span><br><?php echo e($invoice['date'] ?: '-'); ?></td>
                    <td><span class="label">Paid</span><br><?php echo e($invoice['paid_at'] ?: '-'); ?></td>
                    <td><span class="label">Payment Method</span><br><?php echo e($invoice['payment_method'] ?: '-'); ?></td>
                </tr>
            </table>

            <table class="grid block">
                <tr>
                    <td>
                        <div class="label">Supplier</div>
                        <div class="name"><?php echo e($invoice['supplier']['legal_name'] ?? $brand['name']); ?></div>
                        <div class="box">
                            <?php if($address($invoice['supplier']) !== '-'): ?>
                                <?php echo e($address($invoice['supplier'])); ?><br>
                            <?php endif; ?>
                            GSTIN: <?php echo e($invoice['supplier']['gstin'] ?? '-'); ?>

                        </div>
                    </td>
                    <td>
                        <div class="label">Bill To</div>
                        <div class="name"><?php echo e($invoice['customer']['legal_name'] ?? $order->account?->name); ?></div>
                        <div class="box">
                            <?php echo e($invoice['customer']['email'] ?? $order->account?->owner?->email ?? '-'); ?><br>
                            <?php if($address($invoice['customer']) !== '-'): ?>
                                <?php echo e($address($invoice['customer'])); ?><br>
                            <?php endif; ?>
                            GSTIN: <?php echo e($invoice['customer']['gstin'] ?? '-'); ?>

                        </div>
                    </td>
                </tr>
            </table>

            <table class="items">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php echo e($invoice['plan_name']); ?> plan
                        <?php if($invoice['billing_cycle']): ?>
                            <span class="muted">(<?php echo e(ucfirst($invoice['billing_cycle'])); ?>)</span>
                        <?php endif; ?>
                        <br><span class="muted">SAC: <?php echo e($invoice['sac_code'] ?: '-'); ?></span>
                    </td>
                    <td class="right"><?php echo e($money($invoice['base_amount'])); ?></td>
                </tr>
                <?php if($invoice['discount_amount'] > 0): ?>
                    <tr class="discount">
                        <td>Discount <?php if($invoice['discount_code']): ?>(<?php echo e($invoice['discount_code']); ?><?php echo e($invoice['discount_name'] ? ' - '.$invoice['discount_name'] : ''); ?>)<?php endif; ?></td>
                        <td class="right">-<?php echo e($money($invoice['discount_amount'])); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if(($invoice['credit_amount'] ?? 0) > 0): ?>
                    <tr class="discount">
                        <td><?php echo e($invoice['credit_label'] ?? 'Credit for unused time'); ?></td>
                        <td class="right">-<?php echo e($money($invoice['credit_amount'])); ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td>Subtotal (taxable value)</td>
                    <td class="right"><?php echo e($money($invoice['taxable_amount'])); ?></td>
                </tr>
                <?php if($invoice['cgst_amount'] > 0): ?>
                    <tr>
                        <td>CGST @ <?php echo e($splitRate); ?>%</td>
                        <td class="right"><?php echo e($money($invoice['cgst_amount'])); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if($invoice['sgst_amount'] > 0): ?>
                    <tr>
                        <td>SGST @ <?php echo e($splitRate); ?>%</td>
                        <td class="right"><?php echo e($money($invoice['sgst_amount'])); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if($invoice['igst_amount'] > 0): ?>
                    <tr>
                        <td>IGST @ <?php echo e($fullRate); ?>%</td>
                        <td class="right"><?php echo e($money($invoice['igst_amount'])); ?></td>
                    </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td>Total</td>
                    <td class="right"><?php echo e($money($invoice['total_amount'])); ?></td>
                </tr>
            </tbody>
            </table>

            <?php if($invoice['show_payment_instructions']): ?>
                <div class="payment-box">
                    <div class="payment-title">
                        <div class="label">Pay By</div>
                        <div class="name">Bank / UPI</div>
                    </div>
                    <?php if($configuredPaymentRows->isNotEmpty()): ?>
                        <table class="payment-grid">
                            <?php $__currentLoopData = $configuredPaymentRows->chunk(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $chunk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <?php $__currentLoopData = $chunk; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <td><span class="label"><?php echo e($row[0]); ?></span><br><?php echo e($row[1]); ?></td>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    <?php if($chunk->count() === 1): ?>
                                        <td></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </table>
                    <?php else: ?>
                        <div class="empty-payment">Payment details are not configured. Contact billing support before transferring funds.</div>
                    <?php endif; ?>
                    <div class="payment-note muted">
                        <?php if($invoice['has_invoice_number'] ?? false): ?>
                            Mention invoice number <?php echo e($invoice['number']); ?> with your transfer, then upload your receipt from Billing.
                        <?php else: ?>
                            After paying, upload your receipt from Billing.
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="footer">
        This is a computer-generated invoice from <?php echo e($brand['name']); ?>. Please keep it for your tax and payment records.
    </div>
</body>
</html>
<?php /**PATH /workspace/oss/repo/resources/views/billing/invoice-pdf.blade.php ENDPATH**/ ?>