<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\Ntb\ComplaintWorkflowService;
use App\Services\Ntb\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SmsComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintWorkflowService $workflow,
        private readonly SystemSettingsService $settings,
    ) {}

    /**
     * CM-002: Ingest NTB reports via SMS gateway webhook.
     * Expected payload: phone, message, optional metadata fields.
     */
    public function store(Request $request): JsonResponse
    {
        $secret = $this->settings->smsWebhookSecret();
        if ($secret && $request->header('X-SMS-Secret') !== $secret) {
            abort(401, 'Invalid SMS webhook secret.');
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:2000'],
            'title' => ['nullable', 'string', 'max:255'],
            'ntb_category_id' => ['required', 'exists:ntb_categories,id'],
            'corridor_id' => ['required', 'exists:corridors,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'country_origin' => ['required', 'string', 'max:100'],
            'country_destination' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:M,F,NB,NA'],
        ]);

        $complaint = $this->workflow->create([
            'title' => $data['title'] ?? 'SMS report from '.$data['phone'],
            'description' => $data['message'],
            'ntb_category_id' => $data['ntb_category_id'],
            'country_origin' => $data['country_origin'],
            'country_destination' => $data['country_destination'],
            'corridor_id' => $data['corridor_id'],
            'location_id' => $data['location_id'],
            'reporter_phone' => $data['phone'],
            'gender' => $data['gender'] ?? null,
            'submission_channel' => 'SMS',
            'priority' => 'MED',
            'notify_reporter' => true,
        ]);

        return response()->json([
            'message' => 'SMS complaint recorded',
            'complaint_code' => $complaint->complaint_code,
        ], 201);
    }
}
