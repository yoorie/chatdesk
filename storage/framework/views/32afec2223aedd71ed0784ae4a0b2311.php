<?php
    $emailPrimary = trim((string) ($brand['primary_color'] ?? ''));
    if ($emailPrimary === '' || strtolower($emailPrimary) === '#3b82f6') {
        $emailPrimary = '#00A548';
    }
    $emailPrimaryText = $brand['primary_text_color'] ?? '#0f1f19';
?>
<?php if (isset($component)) { $__componentOriginal53f5bb830a22c685f534c50c122d6513 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal53f5bb830a22c685f534c50c122d6513 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.emails.billing.layout','data' => ['title' => 'Payment received']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('emails.billing.layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Payment received']); ?>
    <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#17231f;">Payment received</h1>
    <?php ($paidUntil = $order->account?->subscription?->current_period_end); ?>
    <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">Thank you, we've received your payment. Your <?php echo e($order->plan?->name ?? brand_name()); ?> plan is active<?php echo e($paidUntil ? ' until '.$paidUntil->format('d M Y') : ''); ?>. Your invoice is attached as a PDF.</p>
    <?php echo $__env->make('emails.billing.partials.summary', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <p style="margin:22px 0 0;">
        <a href="<?php echo e($billingUrl); ?>" style="display:inline-block;background:<?php echo e($emailPrimary); ?>;color:<?php echo e($emailPrimaryText); ?>;text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">View Invoice</a>
    </p>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal53f5bb830a22c685f534c50c122d6513)): ?>
<?php $attributes = $__attributesOriginal53f5bb830a22c685f534c50c122d6513; ?>
<?php unset($__attributesOriginal53f5bb830a22c685f534c50c122d6513); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal53f5bb830a22c685f534c50c122d6513)): ?>
<?php $component = $__componentOriginal53f5bb830a22c685f534c50c122d6513; ?>
<?php unset($__componentOriginal53f5bb830a22c685f534c50c122d6513); ?>
<?php endif; ?>
<?php /**PATH /workspace/oss/repo/resources/views/emails/billing/payment-received.blade.php ENDPATH**/ ?>