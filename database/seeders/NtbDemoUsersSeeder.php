<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class NtbDemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $mtt = Institution::query()->where('institution_name', 'Ministry of Trade and Tourism')->first();
        $customs = Institution::query()->where('institution_name', 'Djibouti Customs Authority')->first();

        $users = [
            ['System Administrator', 'sysadmin', 'sysadmin@ntb.dj', 'system_administrator', null],
            ['MTT Administrator', 'mttadmin', 'mttadmin@ntb.dj', 'mtt_admin', $mtt?->id],
            ['National Focal Point', 'focalpoint', 'focal@ntb.dj', 'national_focal_point', $mtt?->id],
            ['Customs Officer', 'customs.agent', 'agent@customs.dj', 'institutional_officer', $customs?->id],
            ['Economic Operator', 'operator', 'operator@trade.dj', 'economic_operator', null],
            ['NMC Member', 'nmc.member', 'nmc@ntb.dj', 'nmc_member', $mtt?->id],
            ['Analyst', 'analyst', 'analyst@ntb.dj', 'analyst', $mtt?->id],
        ];

        foreach ($users as [$name, $username, $email, $role, $institutionId]) {
            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'username' => $username,
                    'full_name' => $name,
                    'password' => Hash::make('password'),
                    'institution_id' => $institutionId,
                    'preferred_language' => str_contains($email, 'operator') ? 'en' : 'fr',
                    'status' => 'active',
                ],
            );

            $user->syncRoles([$role]);
        }
    }
}
