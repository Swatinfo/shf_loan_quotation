<?php

return [
    'groups' => [
        'Settings' => [
            ['slug' => 'view_settings', 'name' => 'View Settings', 'description' => 'View the settings page'],
            ['slug' => 'edit_company_info', 'name' => 'Edit Company Info', 'description' => 'Edit company information'],
            ['slug' => 'edit_banks', 'name' => 'Edit Banks', 'description' => 'Add/edit/remove banks'],
            ['slug' => 'edit_documents', 'name' => 'Edit Documents', 'description' => 'Add/edit/remove required documents'],
            ['slug' => 'edit_tenures', 'name' => 'Edit Tenures', 'description' => 'Add/edit/remove loan tenures'],
            ['slug' => 'edit_charges', 'name' => 'Edit Charges', 'description' => 'Edit bank charges'],
            ['slug' => 'edit_services', 'name' => 'Edit Services', 'description' => 'Edit service charges'],
            ['slug' => 'edit_gst', 'name' => 'Edit GST', 'description' => 'Edit GST percentage'],
        ],
        'Quotations' => [
            ['slug' => 'create_quotation', 'name' => 'Create Quotation', 'description' => 'Create new loan quotations'],
            ['slug' => 'generate_pdf', 'name' => 'Generate PDF', 'description' => 'Generate PDF for quotations'],
            ['slug' => 'view_own_quotations', 'name' => 'View Own Quotations', 'description' => 'View quotations created by self'],
            ['slug' => 'view_all_quotations', 'name' => 'View All Quotations', 'description' => 'View all quotations across users'],
            ['slug' => 'delete_quotations', 'name' => 'Delete Quotations', 'description' => 'Delete quotations'],
            ['slug' => 'download_pdf', 'name' => 'Download PDF', 'description' => 'Download generated PDFs'],
        ],
        'Users' => [
            ['slug' => 'view_users', 'name' => 'View Users', 'description' => 'View the users list'],
            ['slug' => 'create_users', 'name' => 'Create Users', 'description' => 'Create new user accounts'],
            ['slug' => 'edit_users', 'name' => 'Edit Users', 'description' => 'Edit existing user accounts'],
            ['slug' => 'delete_users', 'name' => 'Delete Users', 'description' => 'Delete user accounts'],
            ['slug' => 'assign_roles', 'name' => 'Assign Roles', 'description' => 'Assign roles to users'],
        ],
        'System' => [
            ['slug' => 'change_own_password', 'name' => 'Change Own Password', 'description' => 'Change own password'],
            ['slug' => 'manage_permissions', 'name' => 'Manage Permissions', 'description' => 'Manage role and user permissions'],
            ['slug' => 'view_activity_log', 'name' => 'View Activity Log', 'description' => 'View system activity log'],
        ],
    ],

    'role_defaults' => [
        'super_admin' => '*', // all permissions
        'admin' => [
            'view_settings', 'edit_company_info', 'edit_banks', 'edit_documents',
            'edit_tenures', 'edit_charges', 'edit_services', 'edit_gst',
            'create_quotation', 'generate_pdf', 'view_own_quotations',
            'view_all_quotations', 'delete_quotations', 'download_pdf',
            'view_users', 'create_users', 'edit_users', 'assign_roles',
            'change_own_password', 'view_activity_log',
        ],
        'staff' => [
            'create_quotation', 'generate_pdf', 'view_own_quotations',
            'download_pdf', 'change_own_password',
        ],
    ],
];
