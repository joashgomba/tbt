<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            NtbRolesAndPermissionsSeeder::class,
            NtbMasterDataSeeder::class,
            NtbWorkflowSeeder::class,
            NtbDemoUsersSeeder::class,
            NtbComplaintsSeeder::class,
            NtbReportsSeeder::class,
            NtbUamSeeder::class,
            NtbUamNotificationTemplatesSeeder::class,
            NtbInstitutionLinksSeeder::class,
            NtbIntegrationSeeder::class,
        ]);
    }
}
