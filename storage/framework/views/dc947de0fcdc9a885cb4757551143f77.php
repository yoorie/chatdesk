<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo e($code); ?> — <?php echo e($title); ?></title>
    <script>
        try {
            var theme = localStorage.getItem('ui-theme') || localStorage.getItem('legacy-ui-theme') || 'system';
            document.documentElement.classList.toggle('dark', theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches));
        } catch (_) {}
    </script>
    <style><?php echo file_get_contents(resource_path('css/error-pages.css')); ?></style>
</head>
<body style="margin:0">
    <main class="error-page">
        <section class="error-card" data-accent="<?php echo e($accent ?? 'green'); ?>">
            <div class="error-header">
                <span>
                    <img class="error-logo error-logo-light" src="<?php echo e(url('/images/brand/logo-dark.webp')); ?>" width="94" height="32" alt="<?php echo e(brand_name()); ?>">
                    <img class="error-logo error-logo-dark" src="<?php echo e(url('/images/brand/logo-white.webp')); ?>" width="94" height="32" alt="<?php echo e(brand_name()); ?>">
                </span>
                <span class="error-code"><?php echo e($code); ?></span>
            </div>
            <span class="error-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <?php if($code === '404'): ?>
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    <?php elseif($code === '403'): ?>
                        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11"/><path d="M12 8v4m0 4h.01"/>
                    <?php else: ?>
                        <circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/>
                    <?php endif; ?>
                </svg>
            </span>
            <h1 class="error-title"><?php echo e($title); ?></h1>
            <p class="error-description"><?php echo e($description); ?></p>
            <div class="error-actions">
                <?php if(in_array($code, ['419', '429', '500', '503'], true)): ?>
                    <button type="button" class="error-action error-action-primary" onclick="window.location.reload()">Try again</button>
                    <a href="<?php echo e(url('/')); ?>" class="error-action">Home</a>
                <?php else: ?>
                    <a href="<?php echo e(url('/')); ?>" class="error-action error-action-primary">Home</a>
                    <button type="button" class="error-action" onclick="window.history.length > 1 ? window.history.back() : window.location.assign(<?php echo json_encode(url('/'), 15, 512) ?>)">Back</button>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
<?php /**PATH /workspace/oss/repo/resources/views/errors/layout.blade.php ENDPATH**/ ?>