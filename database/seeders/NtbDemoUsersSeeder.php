<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\MfaMethod;
use App\Models\User;
use Illuminate\Database\Seeder;

class NtbDemoUsersSeeder extends Seeder
{
    /** @var list<string> */
    public const DEMO_EMAILS = [
        'sysadmin@ntb.dj',
        'mttadmin@ntb.dj',
        'focal@ntb.dj',
        'instadmin@customs.dj',
        'agent@customs.dj',
        'nmc@ntb.dj',
        'analyst@ntb.dj',
        'auditor@ntb.dj',
        'operator@trade.dj',
    ];

    /** Roles that require MFA enrollment when NTB_MFA_ENABLED=true. */
    private const MFA_DEMO_ROLES = [
        'system_administrator',
        'mtt_admin',
        'national_focal_point',
        'institutional_administrator',
    ];

    public function run(): void
    {
        $mtt = Institution::query()->where('institution_name', 'Ministry of Trade and Tourism')->first();
        $customs = Institution::query()->where('institution_name', 'Djibouti Customs Authority')->first();

        $users = [
            ['System Administrator', 'sysadmin', 'sysadmin@ntb.dj', 'system_administrator', null],
            ['MTT Administrator', 'mttadmin', 'mttadmin@ntb.dj', 'mtt_admin', $mtt?->id],
            ['National Focal Point', 'focalpoint', 'focal@ntb.dj', 'national_focal_point', $mtt?->id],
            ['Customs Institution Admin', 'customs.admin', 'instadmin@customs.dj', 'institutional_administrator', $customs?->id],
            ['Customs Officer', 'customs.agent', 'agent@customs.dj', 'institutional_officer', $customs?->id],
            ['NMC Member', 'nmc.member', 'nmc@ntb.dj', 'nmc_member', $mtt?->id],
            ['Analyst', 'analyst', 'analyst@ntb.dj', 'analyst', $mtt?->id],
            ['Auditor', 'auditor', 'auditor@ntb.dj', 'auditor', $mtt?->id],
            ['Economic Operator', 'operator', 'operator@trade.dj', 'economic_operator', null, '+25377000001'],
            ['USSD Anonymous Channel', 'ussd.anonymous', 'ussd.anonymous@ntb.dj', 'economic_operator', null, '+25300000000'],
        ];

        foreach ($users as $row) {
            [$name, $username, $email, $role, $institutionId] = $row;
            $phone = $row[5] ?? null;
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => $username,
                    'full_name' => $name,
                    'password' => 'password',
                    'institution_id' => $institutionId,
                    'phone_number' => $phone,
                    'preferred_language' => str_contains($email, 'operator') || str_contains($email, 'customs') ? 'en' : 'fr',
                    'status' => 'active',
                    'failed_login_attempts' => 0,
                    'locked_at' => null,
                    'suspension_reason' => null,
                    'suspended_until' => null,
                    'deactivated_at' => null,
                    'password_must_change' => false,
                    'account_category' => $this->accountCategoryFor($role),
                    'mfa_enrolled' => in_array($role, self::MFA_DEMO_ROLES, true),
                ],
            );

            $user->syncRoles([$role]);
            $this->ensureMfaMethod($user);
        }
    }

    private function accountCategoryFor(string $role): string
    {
        return match ($role) {
            'system_administrator' => 'technical',
            'economic_operator' => 'external',
            'institutional_officer', 'institutional_administrator' => 'institutional',
            default => 'national',
        };
    }

    private function ensureMfaMethod(User $user): void
    {
        if (! $user->mfa_enrolled) {
            return;
        }

        MfaMethod::query()->updateOrCreate(
            ['user_id' => $user->id, 'method' => 'email'],
            [
                'destination' => $user->email,
                'is_primary' => true,
                'is_verified' => true,
                'verified_at' => now(),
            ],
        );
    }
}
