<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Corridor;
use App\Models\Institution;
use App\Models\IntegrationExposedEndpoint;
use App\Models\Location;
use App\Models\NtbCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerIntegrationController extends Controller
{
    private function requireScope(Request $request, string $scope): void
    {
        $client = $request->attributes->get('integration_client');
        abort_unless($client?->hasScope($scope) || $client?->hasScope('*'), 403, 'Insufficient API scope.');
    }

    public function catalog(Request $request): JsonResponse
    {
        return response()->json([
            'portal' => 'NTB Portal',
            'version' => 'v1',
            'authenticated_as' => $request->attributes->get('integration_system')?->system_code,
            'endpoints' => IntegrationExposedEndpoint::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->get(['endpoint_code', 'name', 'http_method', 'path', 'resource_type', 'scopes_required', 'description_en']),
        ]);
    }

    public function complaints(Request $request): JsonResponse
    {
        $this->requireScope($request, 'complaints.read');
        $query = Complaint::query()
            ->with(['category:id,code', 'corridor:id,corridor_code', 'location:id,location_name', 'status:id,code,name_en'])
            ->orderByDesc('created_at');

        if ($request->filled('since')) {
            $query->where('updated_at', '>=', $request->string('since'));
        }

        if ($request->filled('status')) {
            $query->whereHas('status', fn ($q) => $q->where('code', $request->string('status')));
        }

        $paginator = $query->paginate(min(max($request->integer('per_page', 50), 1), 100));

        return response()->json([
            'data' => collect($paginator->items())->map(fn (Complaint $c) => [
                'complaint_code' => $c->complaint_code,
                'title' => $c->title,
                'status' => $c->status?->code,
                'category' => $c->category?->code,
                'corridor' => $c->corridor?->corridor_code,
                'location' => $c->location?->location_name,
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showComplaint(Request $request, string $code): JsonResponse
    {
        $this->requireScope($request, 'complaints.read');
        $complaint = Complaint::query()
            ->with(['category', 'corridor', 'location', 'status', 'assignedInstitution'])
            ->where('complaint_code', $code)
            ->firstOrFail();

        return response()->json([
            'complaint' => [
                'complaint_code' => $complaint->complaint_code,
                'title' => $complaint->title,
                'description' => $complaint->description,
                'status' => $complaint->status?->code,
                'category' => $complaint->category?->only(['code', 'name_en']),
                'corridor' => $complaint->corridor?->only(['corridor_code', 'corridor_name']),
                'location' => $complaint->location?->only(['location_code', 'location_name']),
                'institution' => $complaint->assignedInstitution?->only(['institution_code', 'institution_name']),
                'created_at' => $complaint->created_at,
                'updated_at' => $complaint->updated_at,
            ],
        ]);
    }

    public function institutions(Request $request): JsonResponse
    {
        $this->requireScope($request, 'institutions.read');
        return response()->json([
            'data' => Institution::query()
                ->where('status', 'active')
                ->orderBy('institution_name')
                ->get(['institution_code', 'institution_name', 'name_en', 'country', 'integration_code']),
        ]);
    }

    public function masterData(Request $request): JsonResponse
    {
        $this->requireScope($request, 'master_data.read');
        return response()->json([
            'categories' => NtbCategory::query()->where('is_active', true)->orderBy('sort_order')->get(['code', 'name_en', 'name_fr']),
            'corridors' => Corridor::query()->where('active', true)->orderBy('sort_order')->get(['corridor_code', 'corridor_name', 'name_en']),
            'locations' => Location::query()->where('is_active', true)->orderBy('sort_order')->get(['location_code', 'location_name', 'country', 'latitude', 'longitude']),
        ]);
    }

    public function reportsSummary(Request $request): JsonResponse
    {
        $this->requireScope($request, 'reports.read');
        $terminalStatuses = \App\Models\ComplaintStatus::query()->where('is_terminal', true)->pluck('id');

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'total_complaints' => Complaint::query()->count(),
                'open_complaints' => Complaint::query()->whereNotIn('status_id', $terminalStatuses)->count(),
                'resolved_complaints' => Complaint::query()->whereIn('status_id', $terminalStatuses)->count(),
                'institutions' => Institution::query()->where('status', 'active')->count(),
                'corridors' => Corridor::query()->where('active', true)->count(),
                'locations' => Location::query()->where('is_active', true)->count(),
            ],
        ]);
    }
}
