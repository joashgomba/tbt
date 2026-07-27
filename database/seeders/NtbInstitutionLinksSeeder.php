<?php

namespace Database\Seeders;

use App\Models\Corridor;
use App\Models\Institution;
use App\Models\NtbCategory;
use Illuminate\Database\Seeder;

class NtbInstitutionLinksSeeder extends Seeder
{
    public function run(): void
    {
        $corridorIds = Corridor::query()->where('active', true)->pluck('id')->all();
        $categoryIds = NtbCategory::query()->where('is_active', true)->pluck('id')->all();

        if ($corridorIds === [] && $categoryIds === []) {
            return;
        }

        Institution::query()->where('status', 'active')->each(function (Institution $institution) use ($corridorIds, $categoryIds) {
            if ($corridorIds !== []) {
                $institution->corridors()->syncWithoutDetaching(
                    array_slice($corridorIds, 0, min(2, count($corridorIds)))
                );
            }

            if ($categoryIds !== []) {
                $institution->ntbCategories()->syncWithoutDetaching(
                    array_slice($categoryIds, 0, min(3, count($categoryIds)))
                );
            }
        });
    }
}
