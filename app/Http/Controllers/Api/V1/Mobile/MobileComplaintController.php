<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use App\Services\Ntb\ComplaintReferenceDataService;
use App\Services\Ntb\ComplaintSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintSubmissionService $submissions,
        private readonly ComplaintReferenceDataService $referenceData,
    ) {}

    public function lookups(): JsonResponse
    {
        return response()->json($this->referenceData->publicLookups());
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $complaints = Complaint::query()
            ->with(['category', 'status', 'corridor', 'location'])
            ->where('created_by', $user->id)
            ->orderByDesc('created_at')
            ->limit(min($request->integer('limit', 20), 50))
            ->get();

        return response()->json(['data' => $complaints]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $maxKb = app(\App\Services\Ntb\SystemSettingsService::class)->maxUploadKb();
        $request->validate([
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:'.$maxKb],
        ]);

        try {
            $data = $this->submissions->validatePayload($request->all());
            $complaint = $this->submissions->create(
                $user,
                $data,
                'MOBILE',
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

    public function show(Request $request, string $code): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $complaint = Complaint::query()
            ->with(['category', 'status', 'corridor', 'location', 'attachments', 'histories'])
            ->where('complaint_code', $code)
            ->where('created_by', $user->id)
            ->firstOrFail();

        return response()->json(['complaint' => $this->transform($complaint)]);
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
            'submission_channel' => $complaint->submission_channel,
            'status' => $complaint->status,
            'category' => $complaint->category,
            'corridor' => $complaint->corridor,
            'location' => $complaint->location,
            'created_at' => $complaint->created_at,
            'attachments' => $complaint->relationLoaded('attachments') ? $complaint->attachments : [],
            'histories' => $complaint->relationLoaded('histories') ? $complaint->histories : [],
        ];
    }
}
