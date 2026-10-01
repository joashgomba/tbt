<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NtbRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'complaints.submit',
            'complaints.view_own',
            'complaints.view_all',
            'complaints.update',
            'complaints.validate',
            'complaints.assign',
            'complaints.process',
            'complaints.close',
            'complaints.reopen',
            'complaints.escalate',
            'audit.view',
            'dashboards.operational',
            'dashboards.executive',
            'reports.generate',
            'reports.export',
            'reports.admin',
            'reports.schedule',
            'admin.users',
            'admin.roles',
            'admin.institutions',
            'admin.categories',
            'admin.corridors',
            'admin.locations',
            'admin.slas',
            'admin.notifications',
            'admin.integrations',
            'admin.workflows',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'economic_operator' => [
                'complaints.submit', 'complaints.view_own', 'complaints.update',
            ],
            'national_focal_point' => [
                'complaints.view_all', 'complaints.validate', 'complaints.assign',
                'complaints.escalate', 'complaints.reopen', 'audit.view',
                'dashboards.operational', 'reports.generate', 'reports.export',
                'admin.users',
            ],
            'institutional_officer' => [
                'complaints.view_all', 'complaints.process', 'complaints.close',
                'complaints.escalate', 'dashboards.operational', 'reports.generate',
            ],
            'institutional_administrator' => [
                'complaints.view_all', 'complaints.assign', 'complaints.process',
                'complaints.close', 'complaints.escalate', 'dashboards.operational',
                'admin.users', 'admin.institutions', 'reports.generate', 'reports.export',
            ],
            'nmc_member' => [
                'complaints.view_all', 'complaints.validate', 'complaints.assign',
                'complaints.reopen', 'complaints.escalate', 'audit.view',
                'dashboards.operational', 'dashboards.executive', 'reports.generate', 'reports.export',
            ],
            'analyst' => [
                'complaints.view_all', 'audit.view', 'dashboards.operational',
                'dashboards.executive', 'reports.generate', 'reports.export',
            ],
            'system_administrator' => Permission::all()->pluck('name')->all(),
            'auditor' => [
                'audit.view', 'reports.generate', 'reports.export',
            ],
            'mtt_admin' => [
                'complaints.view_all', 'audit.view', 'dashboards.operational',
                'dashboards.executive', 'reports.generate', 'reports.export',
                'admin.users', 'admin.institutions', 'admin.categories',
                'admin.corridors', 'admin.locations', 'admin.slas', 'admin.notifications',
                'admin.integrations',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($rolePermissions);
        }
    }
}
