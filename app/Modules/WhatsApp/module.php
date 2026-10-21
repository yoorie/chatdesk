<?php

return [
    'key' => 'whatsapp.cloud',
    'name' => 'WhatsApp Cloud',
    'description' => 'Connect and manage Meta WhatsApp Cloud API',
    'enabled_by_default' => true,
    'is_core' => false,
    'icon' => 'MessageCircle',
    'nav' => [
        [
            'label' => 'Inbox',
            'href' => 'app.whatsapp.conversations.index',
            'icon' => 'Inbox',
            'group' => 'messaging'],
        [
            'label' => 'Templates',
            'href' => 'app.whatsapp.templates.index',
            'icon' => 'FileText',
            'group' => 'messaging'],
        [
            'label' => 'List messages',
            'href' => 'app.whatsapp.lists.index',
            'icon' => 'List',
            'group' => 'messaging'],
        [
            'label' => 'Quick replies',
            'href' => 'app.quick-replies.index',
            'icon' => 'Zap',
            'group' => 'messaging'],
        [
            'label' => 'WhatsApp Flows',
            'href' => 'app.whatsapp.flows.index',
            'icon' => 'GitBranch',
            'group' => 'messaging'],
        [
            'label' => 'WhatsApp account',
            'href' => 'app.whatsapp.connections.index',
            'icon' => 'Link2',
            'group' => 'settings']]];
