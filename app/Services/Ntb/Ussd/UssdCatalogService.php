<?php

namespace App\Services\Ntb\Ussd;

use App\Models\Location;
use App\Models\NtbCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UssdCatalogService
{
    /** @return Collection<int, NtbCategory> */
    public function rootCategories(int $offset = 0, int $pageSize = 5): Collection
    {
        return NtbCategory::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->skip($offset)
            ->take($pageSize)
            ->get();
    }

    public function rootCategoriesHasMore(int $offset, int $pageSize = 5): bool
    {
        return NtbCategory::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->count() > $offset + $pageSize;
    }

    /** @return list<string> */
    public function issuesForCategory(NtbCategory $category, string $locale): array
    {
        $children = NtbCategory::query()
            ->where('is_active', true)
            ->where('parent_id', $category->id)
            ->orderBy('sort_order')
            ->get();

        if ($children->isNotEmpty()) {
            return $children->map(fn (NtbCategory $c) => $c->displayName($locale))->all();
        }

        $fallback = config('ussd.issues_by_category_code.'.$category->code, ['Other / Not listed']);

        return is_array($fallback) ? $fallback : ['Other / Not listed'];
    }

    /** @return list<array{key: string, label: string}> */
    public function locationTypeOptions(string $locale): array
    {
        $labels = [
            'port' => ['en' => 'Port / port area', 'fr' => 'Port / zone portuaire'],
            'border_post' => ['en' => 'Border post', 'fr' => 'Poste frontalier'],
            'road' => ['en' => 'Road / corridor', 'fr' => 'Route / corridor'],
            'office' => ['en' => 'Government office', 'fr' => 'Administration publique'],
            'other' => ['en' => 'Other', 'fr' => 'Autre'],
        ];

        return collect(config('ussd.location_types', []))
            ->sortBy('sort')
            ->values()
            ->map(fn (array $row) => [
                'key' => $row['key'],
                'label' => $labels[$row['key']][$locale] ?? $row['key'],
            ])
            ->all();
    }

    /** @return Collection<int, Location> */
    public function locationsForTypeKey(string $typeKey, int $offset = 0, int $pageSize = 5): Collection
    {
        $types = collect(config('ussd.location_types', []))
            ->firstWhere('key', $typeKey)['types'] ?? [];

        $query = Location::query()->where('is_active', true)->orderBy('sort_order');

        if ($typeKey === 'other' || $types === []) {
            return $query->skip($offset)->take($pageSize)->get();
        }

        return $query->whereIn('location_type', $types)->skip($offset)->take($pageSize)->get();
    }

    public function locationsHasMore(string $typeKey, int $offset, int $pageSize = 5): bool
    {
        $types = collect(config('ussd.location_types', []))
            ->firstWhere('key', $typeKey)['types'] ?? [];

        $query = Location::query()->where('is_active', true);
        if ($typeKey !== 'other' && $types !== []) {
            $query->whereIn('location_type', $types);
        }

        return $query->count() > $offset + $pageSize;
    }

    public function trimForUssd(string $text, int $max = null): string
    {
        $max ??= (int) config('ussd.max_menu_response_chars', 182);

        return Str::limit($text, $max, '…');
    }
}
