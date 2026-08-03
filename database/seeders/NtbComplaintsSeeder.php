<?php

namespace Database\Seeders;

use App\Enums\ComplaintStatusCode;
use App\Models\ActivitySector;
use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\Corridor;
use App\Models\Institution;
use App\Models\Location;
use App\Models\NtbCategory;
use App\Models\SlaTracking;
use App\Models\User;
use App\Services\Ntb\ComplaintWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NtbComplaintsSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = app(ComplaintWorkflowService::class);

        $corridor = Corridor::query()->where('corridor_code', 'ETH-DJ')->firstOrFail();
        $galafi = Location::query()->where('location_name', 'Galafi Border Post')->firstOrFail();
        $port = Location::query()->where('location_name', 'Port of Djibouti')->firstOrFail();
        $customs = Institution::query()->where('institution_name', 'Djibouti Customs Authority')->first();
        $sps = Institution::query()->where('institution_name', 'National SPS Authority')->first();
        $focal = User::query()->where('email', 'focal@ntb.dj')->first();
        $sectorIds = ActivitySector::query()->pluck('id', 'code');

        $samples = [
            [
                'title' => 'Repeated customs document requests at Galafi',
                'description' => 'Exporter reports three separate requests for the same transit declaration over 48 hours, causing perishable coffee beans to spoil.',
                'category' => 'CUST',
                'sector' => 'AGR',
                'location' => $galafi,
                'status' => ComplaintStatusCode::InProcessing,
                'institution' => $customs,
                'channel' => 'WEB',
                'gender' => 'M',
                'reporter_email' => 'exporter@example.dj',
            ],
            [
                'title' => 'Unclear SPS inspection criteria for plant products',
                'description' => 'Importer unable to obtain written criteria for plant product inspection, leading to unpredictable clearance times at the port.',
                'category' => 'SPS',
                'sector' => 'AGR',
                'location' => $port,
                'status' => ComplaintStatusCode::Assigned,
                'institution' => $sps,
                'channel' => 'WEB',
                'gender' => 'F',
                'reporter_phone' => '+25377001234',
            ],
            [
                'title' => 'Export licence delay beyond published SLA',
                'description' => 'Licence application pending 12 business days against a published 5-day SLA for agricultural exports.',
                'category' => 'LIC',
                'sector' => 'AGR',
                'location' => $port,
                'status' => ComplaintStatusCode::UnderValidation,
                'institution' => null,
                'channel' => 'WEB',
                'gender' => 'NA',
            ],
            [
                'title' => 'Discriminatory handling fee at border checkpoint',
                'description' => 'SMS report: truckers charged an undeclared handling fee not listed in official tariff schedule.',
                'category' => 'TAX',
                'sector' => 'LOG',
                'location' => $galafi,
                'status' => ComplaintStatusCode::Submitted,
                'institution' => null,
                'channel' => 'SMS',
                'gender' => null,
                'reporter_phone' => '+25377009999',
            ],
            [
                'title' => 'Transit permit duplication resolved',
                'description' => 'Duplicate permit requirement removed after institutional coordination; case closed with corrective action.',
                'category' => 'TRN',
                'sector' => 'LOG',
                'location' => $galafi,
                'status' => ComplaintStatusCode::Closed,
                'institution' => $customs,
                'channel' => 'WEB',
                'gender' => 'M',
                'closure' => true,
            ],
            [
                'title' => 'Labelling standard dispute — archived reference case',
                'description' => 'Historical TBT case on bilingual labelling requirements, archived after legal retention review.',
                'category' => 'TBT',
                'sector' => 'MFG',
                'location' => $port,
                'status' => ComplaintStatusCode::Archived,
                'institution' => Institution::query()->where('institution_name', 'Ministry of Trade and Tourism')->first(),
                'channel' => 'WEB',
                'gender' => 'F',
                'archived' => true,
            ],
        ];

        DB::transaction(function () use ($samples, $workflow, $corridor, $focal, $sectorIds) {
            foreach ($samples as $sample) {
                if (Complaint::query()->where('title', $sample['title'])->exists()) {
                    continue;
                }

                $category = NtbCategory::query()->where('code', $sample['category'])->firstOrFail();

                $complaint = $workflow->create([
                    'title' => $sample['title'],
                    'description' => $sample['description'],
                    'ntb_category_id' => $category->id,
                    'activity_sector_id' => $sectorIds[$sample['sector'] ?? 'SVC'] ?? null,
                    'country_origin' => 'Ethiopia',
                    'country_destination' => 'Djibouti',
                    'corridor_id' => $corridor->id,
                    'location_id' => $sample['location']->id,
                    'reporter_email' => $sample['reporter_email'] ?? null,
                    'reporter_phone' => $sample['reporter_phone'] ?? null,
                    'gender' => $sample['gender'] ?? null,
                    'submission_channel' => $sample['channel'],
                    'priority' => 'MED',
                    'notify_reporter' => ! empty($sample['reporter_email'] ?? $sample['reporter_phone'] ?? null),
                ], $focal?->id);

                $this->advanceToStatus($workflow, $complaint, $sample['status'], $sample['institution'], $focal?->id, $sample);
            }
        });
    }

    private function advanceToStatus(
        ComplaintWorkflowService $workflow,
        Complaint $complaint,
        ComplaintStatusCode $target,
        ?Institution $institution,
        ?int $userId,
        array $sample,
    ): void {
        $path = [
            ComplaintStatusCode::Submitted->value => [],
            ComplaintStatusCode::UnderValidation->value => ['under_validation'],
            ComplaintStatusCode::Assigned->value => ['under_validation', 'assigned'],
            ComplaintStatusCode::InProcessing->value => ['under_validation', 'assigned', 'in_processing'],
            ComplaintStatusCode::Closed->value => ['under_validation', 'assigned', 'in_processing', 'resolved', 'closed'],
            ComplaintStatusCode::Archived->value => ['under_validation', 'assigned', 'in_processing', 'resolved', 'closed', 'archived'],
        ];

        foreach ($path[$target->value] ?? [] as $code) {
            $complaint->refresh(['status', 'category']);
            $extra = [];

            if ($code === 'assigned' && $institution) {
                $extra = [
                    'assigned_institution_id' => $institution->id,
                ];
            }

            if ($code === 'resolved') {
                $extra['resolution_note'] = 'Corrective measures implemented and verified with the reporter.';
            }

            if ($code === 'closed') {
                $extra['closure_summary'] = 'Case closed after verification of corrective action.';
            }

            $workflow->transition($complaint, $code, $userId, 'Seeded workflow step', $extra);
        }

        if ($target === ComplaintStatusCode::InProcessing && random_int(0, 1)) {
            SlaTracking::query()->where('complaint_id', $complaint->id)->latest()->limit(1)->update([
                'is_breached' => true,
                'breach_hours' => 4,
            ]);
        }
    }
}
