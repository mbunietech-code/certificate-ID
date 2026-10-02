<?php

/*
|--------------------------------------------------------------------------
| Roles & permissions
|--------------------------------------------------------------------------
| Single source of truth for permission slugs. Every slug becomes a Gate
| ability (see AppServiceProvider). The "roles" map is the default
| assignment written by the RolePermissionSeeder; the super admin can
| change the assignments later from Users & Roles.
*/

return [

    'super_admin_role' => 'super_admin',

    'permissions' => [
        'Schools' => [
            'schools.view' => 'View schools',
            'schools.manage' => 'Create, edit and activate/deactivate schools',
        ],
        'Students' => [
            'students.view' => 'View students',
            'students.create' => 'Add students',
            'students.update' => 'Edit students',
            'students.delete' => 'Delete / deactivate students',
            'students.import' => 'Import students from Excel/CSV',
            'students.export' => 'Export students',
        ],
        'Staff' => [
            'staff.view' => 'View staff',
            'staff.manage' => 'Add, edit and delete staff',
        ],
        'Academic years' => [
            'academic_years.view' => 'View academic years',
            'academic_years.manage' => 'Manage academic years',
        ],
        'Templates' => [
            'templates.view' => 'View ID & certificate templates',
            'templates.manage' => 'Create, design and delete templates',
        ],
        'ID cards' => [
            'id_cards.view' => 'View issued ID cards',
            'id_cards.generate' => 'Generate ID cards',
            'id_cards.revoke' => 'Revoke ID cards',
        ],
        'Certificates' => [
            'certificates.view' => 'View issued certificates',
            'certificates.generate' => 'Generate certificates',
            'certificates.revoke' => 'Revoke certificates',
        ],
        'Printing' => [
            'print.view' => 'View print center & print history',
            'print.execute' => 'Print, reprint and download print jobs',
        ],
        'Reports' => [
            'reports.view' => 'View and export reports',
        ],
        'Users' => [
            'users.view' => 'View users',
            'users.manage' => 'Create and edit users of the school',
        ],
        'Settings' => [
            'settings.school' => 'Edit school profile, branding & numbering',
            'settings.system' => 'Edit system settings',
            'roles.manage' => 'Edit role permissions',
        ],
        'Audit' => [
            'audit.view' => 'View audit logs',
        ],
    ],

    'roles' => [
        'super_admin' => [
            'name' => 'Super Admin',
            'description' => 'Full access to every school and system setting.',
            'permissions' => ['*'],
        ],
        'school_admin' => [
            'name' => 'School Admin',
            'description' => 'Manages everything inside their own school.',
            'permissions' => [
                'students.*', 'staff.*', 'academic_years.*', 'templates.*', 'id_cards.*',
                'certificates.*', 'print.*', 'reports.view', 'users.*', 'settings.school', 'audit.view',
            ],
        ],
        'registrar' => [
            'name' => 'Registrar',
            'description' => 'Manages students, staff and certificates.',
            'permissions' => [
                'students.*', 'staff.*', 'academic_years.view', 'templates.view', 'id_cards.view',
                'certificates.view', 'certificates.generate', 'print.view', 'reports.view',
            ],
        ],
        'printer' => [
            'name' => 'Printer',
            'description' => 'Operates the print center.',
            'permissions' => [
                'students.view', 'staff.view', 'academic_years.view', 'templates.view', 'id_cards.view',
                'id_cards.generate', 'certificates.view', 'print.*',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'description' => 'Read-only access.',
            'permissions' => [
                'students.view', 'staff.view', 'academic_years.view', 'templates.view', 'id_cards.view',
                'certificates.view', 'print.view', 'reports.view',
            ],
        ],
    ],

    // Permissions a non-super-admin role can never hold, regardless of what is ticked in the UI.
    'super_admin_only' => ['schools.manage', 'settings.system', 'roles.manage'],
];
