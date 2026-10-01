<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ntb\ComplaintSubmissionService;
use App\Services\Ntb\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SmsComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintSubmissionService $submissions,
        private readonly SystemSettingsService $settings,
    ) {}

    /**
     * Ingest NTB reports via SMS gateway webhook (structured payload from middleware).
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
            'occurrence_date' => ['nullable', 'date', 'before_or_equal:today'],
            'country_affected' => ['nullable', 'string', 'max:100'],
            'ntb_category_id' => ['required', 'exists:ntb_categories,id'],
            'corridor_id' => ['required', 'exists:corridors,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'country_origin' => ['required', 'string', 'max:100'],
            'country_destination' => ['required', 'string', 'max:100'],
            'product_description' => ['nullable', 'string', 'max:5000'],
            'product_country_origin' => ['nullable', 'string', 'max:100'],
            'product_value_currency' => ['nullable', 'string', 'max:3'],
            'product_value_amount' => ['nullable', 'numeric', 'min:0'],
            'gender' => ['nullable', 'in:M,F,NB,NA'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $user = null;
        if (! empty($data['user_id'])) {
            $user = User::query()->find($data['user_id']);
        }
        if (! $user) {
            $user = User::query()
                ->where('phone_number', $data['phone'])
                ->where('status', 'active')
                ->first();
        }

        $payload = [
            'title' => $data['title'] ?? 'SMS report from '.$data['phone'],
            'description' => $data['message'],
            'occurrence_date' => $data['occurrence_date'] ?? now()->toDateString(),
            'country_affected' => $data['country_affected'] ?? $data['country_destination'],
            'ntb_category_id' => $data['ntb_category_id'],
            'country_origin' => $data['country_origin'],
            'country_destination' => $data['country_destination'],
            'corridor_id' => $data['corridor_id'],
            'location_id' => $data['location_id'],
            'product_description' => $data['product_description'] ?? null,
            'product_country_origin' => $data['product_country_origin'] ?? null,
            'product_value_currency' => $data['product_value_currency'] ?? null,
            'product_value_amount' => $data['product_value_amount'] ?? null,
        ];

        $validated = $this->submissions->validatePayload($payload);

        if ($user && $user->can('complaints.submit')) {
            $complaint = $this->submissions->create($user, $validated, 'SMS', true);
        } else {
            $complaint = app(\App\Services\Ntb\ComplaintWorkflowService::class)->create([
                'title' => Str::limit($data['title'] ?? $validated['description'], 255, ''),
                'description' => $validated['description'],
                'occurrence_date' => $validated['occurrence_date'],
                'country_affected' => $validated['country_affected'],
                'ntb_category_id' => $validated['ntb_category_id'],
                'country_origin' => $validated['country_origin'],
                'country_destination' => $validated['country_destination'],
                'corridor_id' => $validated['corridor_id'],
                'location_id' => $validated['location_id'],
                'product_description' => $validated['product_description'] ?? null,
                'product_country_origin' => $validated['product_country_origin'] ?? null,
                'product_value_currency' => $validated['product_value_currency'] ?? null,
                'product_value_amount' => $validated['product_value_amount'] ?? null,
                'reporter_phone' => $data['phone'],
                'gender' => $data['gender'] ?? null,
                'submission_channel' => 'SMS',
                'priority' => 'MED',
                'notify_reporter' => true,
            ]);
        }

        return response()->json([
            'message' => 'SMS complaint recorded',
            'complaint_code' => $complaint->complaint_code,
        ], 201);
    }
}
