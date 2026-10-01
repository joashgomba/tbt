<?php

namespace App\Services\Ntb\Ussd;

use App\Models\Corridor;
use App\Models\Location;
use Illuminate\Support\Str;

class UssdComplaintDraftBuilder
{
    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    public function toSubmissionPayload(array $draft, string $msisdn): array
    {
        $issue = trim((string) ($draft['issue_label'] ?? 'NTB report'));
        $extra = trim((string) ($draft['short_description'] ?? ''));
        $traderNote = $this->traderNote($draft, $msisdn);

        $description = collect([$issue, $extra, $traderNote])->filter()->implode('. ');

        $locationId = (int) ($draft['location_id'] ?? 0);
        $corridorId = (int) ($draft['corridor_id'] ?? 0);

        if (! $locationId) {
            $location = $this->defaultLocation($draft['location_type_key'] ?? 'other');
            $locationId = $location?->id;
            $corridorId = $corridorId ?: ($location?->corridor_id ?? $this->defaultCorridorId());
        }

        if (! $corridorId) {
            $corridorId = $this->defaultCorridorId();
        }

        $defaults = config('ussd.default_countries', []);

        return [
            'title' => Str::limit($issue, 255, ''),
            'description' => Str::limit($description, 5000, ''),
            'occurrence_date' => now()->toDateString(),
            'country_affected' => $defaults['affected'] ?? 'Djibouti',
            'ntb_category_id' => (int) ($draft['ntb_category_id'] ?? 0),
            'country_origin' => $defaults['origin'] ?? 'Ethiopia',
            'country_destination' => $defaults['destination'] ?? 'Djibouti',
            'corridor_id' => $corridorId,
            'location_id' => $locationId,
            'product_description' => null,
        ];
    }

    /** @param  array<string, mixed>  $draft */
    private function traderNote(array $draft, string $msisdn): string
    {
        $type = $draft['trader_id_type'] ?? 'mobile';
        $value = $draft['trader_id_value'] ?? $msisdn;

        return match ($type) {
            'licence' => 'Trader licence/patent: '.$value,
            'trader_id' => 'Trader ID: '.$value,
            'mobile' => 'Contact mobile: '.$value,
            'anonymous' => 'Submitted anonymously via USSD',
            default => 'Reporter MSISDN: '.$msisdn,
        };
    }

    private function defaultCorridorId(): int
    {
        $code = config('ussd.default_corridor_code', 'ETH-DJ');

        return (int) (Corridor::query()->where('corridor_code', $code)->value('id')
            ?? Corridor::query()->where('active', true)->orderBy('sort_order')->value('id')
            ?? 0);
    }

    private function defaultLocation(?string $locationTypeKey): ?Location
    {
        $types = collect(config('ussd.location_types', []))
            ->firstWhere('key', $locationTypeKey)['types'] ?? [];

        $query = Location::query()->where('is_active', true)->orderBy('sort_order');

        if ($types !== []) {
            $query->whereIn('location_type', $types);
        }

        return $query->first();
    }
}
