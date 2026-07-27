<?php

namespace App\Services\Ntb;

use App\Models\Complaint;
use Illuminate\Support\Str;

class DuplicateDetectionService
{
    public function __construct(private readonly SystemSettingsService $settings) {}

    /** @return array{is_duplicate: bool, matches: list<array<string, mixed>>} */
    public function check(array $data): array
    {
        $windowDays = $this->settings->duplicateWindowDays();
        $since = now()->subDays($windowDays);

        $query = Complaint::query()
            ->with(['status:id,code', 'category:id,code'])
            ->where('created_at', '>=', $since)
            ->where('ntb_category_id', $data['ntb_category_id']);

        if (! empty($data['location_id'])) {
            $query->where('location_id', $data['location_id']);
        }

        if (! empty($data['corridor_id'])) {
            $query->where('corridor_id', $data['corridor_id']);
        }

        $candidates = $query->latest()->limit(20)->get(['id', 'complaint_code', 'title', 'description', 'status_id', 'created_at']);

        $title = Str::lower(trim($data['title'] ?? ''));
        $description = Str::lower(trim($data['description'] ?? ''));

        $matches = $candidates->filter(function (Complaint $c) use ($title, $description) {
            $cTitle = Str::lower(trim($c->title));
            $cDesc = Str::lower(trim($c->description));

            if ($cTitle === $title) {
                return true;
            }

            similar_text($description, $cDesc, $pct);

            return $pct >= 85;
        })->map(fn (Complaint $c) => [
            'id' => $c->id,
            'complaint_code' => $c->complaint_code,
            'title' => $c->title,
            'status' => $c->status?->code,
            'created_at' => $c->created_at?->toIso8601String(),
        ])->values()->all();

        return [
            'is_duplicate' => count($matches) > 0,
            'matches' => $matches,
        ];
    }
}
