<?php

namespace Database\Seeders;

use App\Models\AccessReview;
use App\Models\Delegation;
use App\Models\Institution;
use App\Models\InstitutionUnit;
use App\Models\MfaMethod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class NtbUamSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleMeta = [
            'system_administrator' => ['code' => 'SYS_ADMIN', 'title_en' => 'System Administrator', 'title_fr' => 'Administrateur système', 'category' => 'technical', 'scope' => 'global', 'is_protected' => true],
            'mtt_admin' => ['code' => 'MTT_ADMIN', 'title_en' => 'MTT Administrator', 'title_fr' => 'Administrateur MTT', 'category' => 'national', 'scope' => 'national', 'is_protected' => true],
            'national_focal_point' => ['code' => 'NFP', 'title_en' => 'National Focal Point', 'title_fr' => 'Point focal national', 'category' => 'national', 'scope' => 'national', 'is_protected' => false],
            'institutional_administrator' => ['code' => 'INST_ADMIN', 'title_en' => 'Institutional Administrator', 'title_fr' => 'Administrateur institutionnel', 'category' => 'institutional', 'scope' => 'institution', 'is_protected' => false],
            'institutional_officer' => ['code' => 'INST_OFFICER', 'title_en' => 'Institutional Officer', 'title_fr' => 'Agent institutionnel', 'category' => 'institutional', 'scope' => 'institution', 'is_protected' => false],
            'nmc_member' => ['code' => 'NMC', 'title_en' => 'NMC Member', 'title_fr' => 'Membre CNM', 'category' => 'national', 'scope' => 'national', 'is_protected' => false],
            'analyst' => ['code' => 'ANALYST', 'title_en' => 'Analyst', 'title_fr' => 'Analyste', 'category' => 'analytical', 'scope' => 'national', 'is_protected' => false],
            'auditor' => ['code' => 'AUDITOR', 'title_en' => 'Auditor', 'title_fr' => 'Auditeur', 'category' => 'audit', 'scope' => 'national', 'is_protected' => false],
            'economic_operator' => ['code' => 'OPERATOR', 'title_en' => 'Economic Operator', 'title_fr' => 'Opérateur économique', 'category' => 'external', 'scope' => 'own', 'is_protected' => false],
        ];

        foreach ($roleMeta as $name => $meta) {
            $role = Role::findByName($name, 'web');
            $role->update($meta);
        }

        $permMeta = [
            'admin.users' => ['resource' => 'user', 'action' => 'administer', 'data_scope' => 'institution', 'title_en' => 'Manage users', 'title_fr' => 'Gérer les utilisateurs'],
            'admin.roles' => ['resource' => 'role', 'action' => 'administer', 'data_scope' => 'global', 'title_en' => 'Manage roles', 'title_fr' => 'Gérer les rôles'],
            'admin.institutions' => ['resource' => 'institution', 'action' => 'administer', 'data_scope' => 'national', 'title_en' => 'Manage institutions', 'title_fr' => 'Gérer les institutions'],
            'complaints.view_all' => ['resource' => 'complaint', 'action' => 'read', 'data_scope' => 'national', 'title_en' => 'View all complaints', 'title_fr' => 'Voir toutes les plaintes'],
        ];

        foreach ($permMeta as $name => $meta) {
            Permission::findOrCreate($name, 'web');
            DB::table('permissions')->where('name', $name)->update($meta);
        }

        $institutions = Institution::all();
        foreach ($institutions as $inst) {
            if (! $inst->institution_code) {
                $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $inst->institution_name), 0, 6));
                $inst->update([
                    'institution_code' => $code.'-'.str_pad((string) $inst->id, 2, '0', STR_PAD_LEFT),
                    'name_en' => $inst->institution_name,
                    'name_fr' => $inst->institution_name,
                ]);
            }
        }

        $mtt = Institution::query()->where('institution_name', 'like', '%Trade%')->first();
        $customs = Institution::query()->where('institution_name', 'like', '%Customs%')->first();

        if ($mtt) {
            InstitutionUnit::query()->updateOrCreate(
                ['institution_id' => $mtt->id, 'unit_code' => 'NFP-OFFICE'],
                ['name_en' => 'National Focal Point Office', 'name_fr' => 'Bureau du point focal national', 'is_active' => true],
            );
        }

        if ($customs) {
            InstitutionUnit::query()->updateOrCreate(
                ['institution_id' => $customs->id, 'unit_code' => 'OPS-UNIT'],
                ['name_en' => 'Operations Unit', 'name_fr' => 'Unité opérationnelle', 'is_active' => true],
            );
        }

        User::query()
            ->whereIn('email', NtbDemoUsersSeeder::DEMO_EMAILS)
            ->where('mfa_enrolled', true)
            ->each(function (User $user) {
                MfaMethod::query()->updateOrCreate(
                    ['user_id' => $user->id, 'method' => 'email'],
                    [
                        'destination' => $user->email,
                        'is_primary' => true,
                        'is_verified' => true,
                        'verified_at' => now(),
                    ],
                );
            });

        $focal = User::query()->where('email', 'focal@ntb.dj')->first();
        $agent = User::query()->where('email', 'agent@customs.dj')->first();

        if ($focal && $agent) {
            Delegation::query()->updateOrCreate(
                ['delegator_id' => $focal->id, 'delegate_id' => $agent->id, 'status' => 'active'],
                [
                    'starts_at' => now()->subDays(2),
                    'ends_at' => now()->addDays(14),
                    'reason' => 'Annual leave coverage',
                    'permissions' => ['complaints.validate', 'complaints.assign'],
                ],
            );
        }

        if ($mtt) {
            AccessReview::query()->updateOrCreate(
                ['title' => 'Q3 Institutional Access Review', 'institution_id' => $mtt->id],
                [
                    'status' => 'scheduled',
                    'due_at' => now()->addMonth(),
                    'reviewer_id' => $focal?->id,
                ],
            );
        }
    }
}
