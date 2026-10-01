<?php

namespace App\Services\Ntb;

use App\Models\Corridor;
use App\Models\Location;
use App\Models\NtbCategory;

class ComplaintReferenceDataService
{
    public function __construct(private readonly SystemSettingsService $settings) {}

    /** @return array<string, mixed> */
    public function publicLookups(): array
    {
        return [
            'categories' => NtbCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name_en', 'name_fr', 'description_en', 'description_fr', 'icon']),
            'corridors' => Corridor::query()->where('active', true)->orderBy('sort_order')->orderBy('corridor_code')->get(['id', 'corridor_code', 'corridor_name', 'name_en', 'name_fr', 'description_en', 'description_fr', 'origin_country', 'destination_country', 'primary_mode', 'icon']),
            'locations' => Location::query()->where('is_active', true)->orderBy('sort_order')->orderBy('location_name')->get(['id', 'location_code', 'location_name', 'name_en', 'name_fr', 'description_en', 'description_fr', 'corridor_id', 'border_post', 'location_type', 'country', 'latitude', 'longitude']),
            'countries' => config('ntb.countries', []),
            'currencies' => config('ntb.currencies', []),
            'settings' => [
                'max_attachment_size_kb' => $this->settings->maxUploadKb(),
                'max_attachments' => 5,
                'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'docx'],
            ],
        ];
    }
}
