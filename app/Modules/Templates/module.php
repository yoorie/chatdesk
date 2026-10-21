<?php

return [
    'key' => 'templates',
    'name' => 'Templates',
    'description' => 'Manage WhatsApp message templates',
    'enabled_by_default' => false,
    'is_core' => false,
    'icon' => 'FileText',
    // No nav: WhatsApp module already shows "Templates" (app.whatsapp.templates.index). Avoid duplicate sidebar entry.
    'nav' => [],
];
