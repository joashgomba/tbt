<?php

namespace Database\Seeders;

use App\Models\ActivitySector;
use App\Models\Complaint;
use Illuminate\Database\Seeder;

class NtbActivitySectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            ['AGR', 'Agriculture & agri-food', 'Agriculture et agroalimentaire', 10],
            ['MFG', 'Manufacturing & industry', 'Industrie et fabrication', 20],
            ['LOG', 'Logistics & transport', 'Logistique et transport', 30],
            ['RET', 'Retail & consumer goods', 'Commerce de détail et biens de consommation', 40],
            ['ENR', 'Energy & mining', 'Énergie et mines', 50],
            ['SVC', 'Services & trade facilitation', 'Services et facilitation commerciale', 60],
        ];

        foreach ($sectors as [$code, $en, $fr, $sort]) {
            ActivitySector::query()->updateOrCreate(
                ['code' => $code],
                ['name_en' => $en, 'name_fr' => $fr, 'sort_order' => $sort, 'is_active' => true],
            );
        }

        $categorySectorMap = [
            'SPS' => 'AGR',
            'TBT' => 'MFG',
            'CUST' => 'LOG',
            'LIC' => 'AGR',
            'TAX' => 'LOG',
            'TRN' => 'LOG',
        ];

        $sectorIds = ActivitySector::query()->pluck('id', 'code');

        Complaint::query()
            ->with('category:id,code')
            ->whereNull('activity_sector_id')
            ->each(function (Complaint $complaint) use ($categorySectorMap, $sectorIds) {
                $code = $categorySectorMap[$complaint->category?->code] ?? 'SVC';
                $complaint->update(['activity_sector_id' => $sectorIds[$code] ?? null]);
            });
    }
}
