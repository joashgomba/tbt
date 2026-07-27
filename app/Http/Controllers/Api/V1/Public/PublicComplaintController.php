<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Corridor;
use App\Models\Location;
use App\Models\NtbCategory;
use App\Services\Ntb\ComplaintWorkflowService;
use App\Services\Ntb\DuplicateDetectionService;
use App\Services\Ntb\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PublicComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintWorkflowService $workflow,
        private readonly DuplicateDetectionService $duplicates,
        private readonly SystemSettingsService $settings,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $maxKb = $this->settings->maxUploadKb();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'ntb_category_id' => ['required', 'exists:ntb_categories,id'],
            'country_origin' => ['required', 'string', 'max:100'],
            'country_destination' => ['required', 'string', 'max:100'],
            'corridor_id' => ['required', 'exists:corridors,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'reporter_email' => ['nullable', 'email', 'max:255'],
            'reporter_phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:M,F,NB,NA'],
            'notify_reporter' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:'.$maxKb],
        ]);

        if (! empty($data['notify_reporter']) || ! empty($data['reporter_email']) || ! empty($data['reporter_phone'])) {
            if (empty($data['reporter_email']) && empty($data['reporter_phone'])) {
                throw ValidationException::withMessages([
                    'reporter_email' => ['Provide a valid email or phone number to receive notifications.'],
                ]);
            }
        }

        if (! empty($data['reporter_phone']) && ! preg_match('/^\+?[0-9\s\-()]{7,30}$/', $data['reporter_phone'])) {
            throw ValidationException::withMessages([
                'reporter_phone' => ['Enter a valid telephone number.'],
            ]);
        }

        $duplicateCheck = $this->duplicates->check($data);
        if ($duplicateCheck['is_duplicate'] && ! $request->boolean('confirm_duplicate')) {
            return response()->json([
                'message' => 'A similar complaint was recently submitted.',
                'duplicate_warning' => true,
                'matches' => $duplicateCheck['matches'],
            ], 409);
        }

        $complaint = $this->workflow->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'ntb_category_id' => $data['ntb_category_id'],
            'country_origin' => $data['country_origin'],
            'country_destination' => $data['country_destination'],
            'corridor_id' => $data['corridor_id'],
            'location_id' => $data['location_id'],
            'reporter_name' => $data['reporter_name'] ?? null,
            'reporter_email' => $data['reporter_email'] ?? null,
            'reporter_phone' => $data['reporter_phone'] ?? null,
            'gender' => $data['gender'] ?? null,
            'notify_reporter' => (bool) ($data['notify_reporter'] ?? false),
            'submission_channel' => 'WEB',
            'priority' => 'MED',
        ], $request->user()?->id);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store("complaints/{$complaint->id}", 'public');
                $complaint->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $request->user()?->id,
                ]);
            }
        }

        return response()->json([
            'message' => 'Complaint submitted successfully',
            'complaint' => $this->transform($complaint->fresh(['category', 'status', 'corridor', 'location', 'attachments'])),
        ], 201);
    }

    public function show(string $code): JsonResponse
    {
        $complaint = Complaint::query()
            ->with(['category', 'status', 'corridor', 'location', 'assignedInstitution', 'histories'])
            ->where('complaint_code', $code)
            ->firstOrFail();

        return response()->json(['complaint' => $this->transform($complaint)]);
    }

    public function lookups(): JsonResponse
    {
        return response()->json([
            'categories' => NtbCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('code')->get(['id', 'code', 'name_en', 'name_fr', 'description_en', 'description_fr', 'icon']),
            'corridors' => Corridor::query()->where('active', true)->orderBy('sort_order')->orderBy('corridor_code')->get(['id', 'corridor_code', 'corridor_name', 'name_en', 'name_fr', 'description_en', 'description_fr', 'origin_country', 'destination_country', 'primary_mode', 'icon']),
            'locations' => Location::query()->where('is_active', true)->orderBy('sort_order')->orderBy('location_name')->get(['id', 'location_code', 'location_name', 'name_en', 'name_fr', 'description_en', 'description_fr', 'corridor_id', 'border_post', 'location_type', 'country', 'latitude', 'longitude']),
            'settings' => [
                'max_attachment_size_kb' => $this->settings->maxUploadKb(),
                'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'docx'],
            ],
        ]);
    }

    private function transform(Complaint $complaint): array
    {
        return [
            'id' => $complaint->id,
            'complaint_code' => $complaint->complaint_code,
            'title' => $complaint->title,
            'description' => $complaint->description,
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
