<?php

return [
    'key' => 'contacts',
    'name' => 'Contacts & CRM',
    'description' => 'Manage contacts, tags, segments, and customer relationships',
    'enabled_by_default' => true,
    'is_core' => false,
    'icon' => 'Users',
    'nav' => [
        [
            'label' => 'Contacts',
            'href' => 'app.contacts.index',
            'icon' => 'Users',
            'group' => 'messaging',
        ],
        [
            'label' => 'Tags',
            'href' => 'app.contacts.tags.index',
            'icon' => 'Tag',
            'group' => 'messaging',
        ],
        [
            'label' => 'Segments',
            'href' => 'app.contacts.segments.index',
            'icon' => 'FolderOpen',
            'group' => 'messaging',
        ],
    ],
];
