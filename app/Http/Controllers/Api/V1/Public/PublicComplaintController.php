<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use App\Services\Ntb\ComplaintReferenceDataService;
use App\Services\Ntb\ComplaintSubmissionService;
use App\Services\Ntb\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintSubmissionService $submissions,
        private readonly ComplaintReferenceDataService $referenceData,
        private readonly SystemSettingsService $settings,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'You must be signed in to submit a complaint.');

        $this->submissions->validateAttachments($request);

        try {
            $data = $this->submissions->validatePayload($request->all());
            /** @var User $user */
            $complaint = $this->submissions->create(
                $user,
                $data,
                'WEB',
                $request->boolean('confirm_duplicate'),
                $request,
            );
        } catch (ValidationException $e) {
            if ($e->errors()['duplicate_warning'] ?? null) {
                return response()->json([
                    'message' => 'A similar complaint was recently submitted.',
                    'duplicate_warning' => true,
                ], 409);
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Complaint submitted successfully',
            'complaint' => $this->transform($complaint),
        ], 201);
    }

    public function show(string $code): JsonResponse
    {
        $complaint = Complaint::query()
            ->with([
                'category',
                'status',
                'corridor',
                'location',
                'assignedInstitution',
                'histories' => fn ($q) => $q->where('action_type', 'STATUS_CHANGED')->orderBy('action_date'),
            ])
            ->where('complaint_code', $code)
            ->firstOrFail();

        return response()->json(['complaint' => $this->transform($complaint)]);
    }

    public function lookups(): JsonResponse
    {
        return response()->json($this->referenceData->publicLookups());
    }

    private function transform(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'complaint_code' => $complaint->complaint_code,
            'title' => $complaint->title,
            'description' => $complaint->description,
            'occurrence_date' => $complaint->occurrence_date?->format('Y-m-d'),
            'country_affected' => $complaint->country_affected,
            'product_description' => $complaint->product_description,
            'product_country_origin' => $complaint->product_country_origin,
            'product_value_currency' => $complaint->product_value_currency,
            'product_value_amount' => $complaint->product_value_amount,
            'priority' => $complaint->priority,
            'status' => $complaint->status,
            'category' => $complaint->category,
            'corridor' => $complaint->corridor,
            'location' => $complaint->location,
            'assigned_institution' => $complaint->assignedInstitution,
            'sla_deadline' => $complaint->sla_deadline,
            'closed_at' => $complaint->closed_at,
            'archived_at' => $complaint->archived_at,
            'created_at' => $complaint->created_at,
            'histories' => $complaint->relationLoaded('histories') ? $complaint->histories : [],
            'attachments' => $complaint->relationLoaded('attachments') ? $complaint->attachments : [],
        ];
    }
}
