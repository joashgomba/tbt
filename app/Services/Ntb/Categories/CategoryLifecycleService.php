<?php

namespace App\Services\Ntb\Categories;

use App\Models\Complaint;
use App\Models\ComplaintStatus;
use App\Models\NtbCategory;
use App\Services\Ntb\AuditLogger;
use Illuminate\Http\Request;

class CategoryLifecycleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function activate(Request $request, NtbCategory $category): NtbCategory
    {
        $old = $category->is_active;
        $category->update(['is_active' => true]);

        $this->audit->log('category.activate', $category, ['is_active' => $old], ['is_active' => true], $request);

        return $category->fresh();
    }

    public function deactivate(Request $request, NtbCategory $category, ?string $reason = null): NtbCategory
    {
        $impact = $this->impactAssessment($category);

        abort_if($impact['open_cases'] > 0, 422, "Cannot deactivate: {$impact['open_cases']} open case(s) use this category.");
        abort_if($impact['active_children'] > 0, 422, 'Deactivate or reassign child categories first.');

        $old = $category->is_active;
        $category->update(['is_active' => false]);

        $this->audit->log('category.deactivate', $category, ['is_active' => $old], [
            'is_active' => false,
            'reason' => $reason,
            'impact' => $impact,
        ], $request);

        return $category->fresh();
    }

    public function impactAssessment(NtbCategory $category): array
    {
        $terminalIds = ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return [
            'open_cases' => Complaint::query()
                ->where('ntb_category_id', $category->id)
                ->whereNotIn('status_id', $terminalIds)
                ->count(),
            'total_cases' => Complaint::query()->where('ntb_category_id', $category->id)->count(),
            'active_children' => NtbCategory::query()
                ->where('parent_id', $category->id)
                ->where('is_active', true)
                ->count(),
            'linked_institutions' => $category->institutions()->count(),
            'sla_configurations' => $category->slaConfigurations()->count(),
        ];
    }
}
