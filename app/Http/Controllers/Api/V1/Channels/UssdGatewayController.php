<?php

namespace App\Http\Controllers\Api\V1\Channels;

use App\Http\Controllers\Controller;
use App\Models\UssdSession;
use App\Models\UssdSessionEvent;
use App\Services\Ntb\Ussd\UssdSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class UssdGatewayController extends Controller
{
    public function __construct(private readonly UssdSessionService $ussd) {}

    /**
     * Primary USSD callback for telco aggregators (Africa's Talking, Djibouti Telecom, etc.).
     */
    public function callback(Request $request): JsonResponse|Response
    {
        $normalized = $this->normalizeGatewayRequest($request);
        $result = $this->ussd->handle(
            $normalized['session_id'],
            $normalized['phone_number'],
            $normalized['text'],
            $normalized['service_code'],
        );

        return $this->formatGatewayResponse($request, $result);
    }

    /**
     * Admin-only simulator endpoint (same business logic as production callback).
     */
    public function simulate(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        $data = $request->validate([
            'session_id' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['required', 'string', 'max:30'],
            'text' => ['nullable', 'string', 'max:500'],
            'service_code' => ['nullable', 'string', 'max:40'],
            'new_session' => ['nullable', 'boolean'],
        ]);

        $sessionId = $data['session_id'] ?? ('SIM-'.Str::uuid());
        if ($request->boolean('new_session')) {
            UssdSession::query()->where('session_id', $sessionId)->delete();
            $data['text'] = '';
        }

        $result = $this->ussd->handle(
            $sessionId,
            $data['phone_number'],
            $data['text'] ?? '',
            $data['service_code'] ?? config('ussd.short_code'),
        );

        return response()->json(array_merge($result, [
            'session_id' => $sessionId,
            'integration' => $this->integrationMeta(),
        ]));
    }

    public function schema(): JsonResponse
    {
        return response()->json(array_merge([
            'channel' => 'USSD',
            'short_code' => config('ussd.short_code'),
            'callback_method' => 'POST',
            'callback_path' => '/api/v1/channels/ussd/callback',
            'simulator_path' => '/api/v1/admin/channels/ussd/simulate',
            'authentication' => 'Header X-USSD-Secret (when ussd_webhook_secret is configured)',
            'request_fields' => [
                'session_id' => 'Unique session id from the telco aggregator',
                'phone_number' => 'MSISDN of the subscriber (also accepts phoneNumber, msisdn)',
                'text' => 'Cumulative user input separated by * (empty on first dial; also userInput, input)',
                'service_code' => 'Optional USSD short code (serviceCode)',
            ],
            'response_formats' => [
                'json' => 'Default — session_active, message, message_type, state, complaint_code',
                'text_plain' => 'Send Accept: text/plain — message prefixed with CON or END (Africa\'s Talking compatible)',
            ],
            'navigation' => [
                'MAIN_MENU', 'SUBMIT_*', 'TRACK_*', 'INFO_*',
            ],
            'audit' => 'Each step is stored in ussd_session_events for troubleshooting.',
        ], ['integration' => $this->integrationMeta()]));
    }

    public function sessions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        $query = UssdSession::query()->with('user:id,full_name,phone_number', 'complaint:id,complaint_code')
            ->orderByDesc('updated_at');

        if ($request->filled('msisdn')) {
            $query->where('msisdn', 'like', '%'.$request->string('msisdn').'%');
        }

        return response()->json([
            'data' => $query->limit(100)->get(),
        ]);
    }

    public function sessionEvents(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.integrations'), 403);

        $session = UssdSession::query()->findOrFail($id);
        $events = UssdSessionEvent::query()
            ->where('ussd_session_id', $session->id)
            ->orderBy('id')
            ->get();

        return response()->json(['session' => $session, 'events' => $events]);
    }

    /** @return array{session_id: string, phone_number: string, text: string, service_code: ?string} */
    private function normalizeGatewayRequest(Request $request): array
    {
        $sessionId = $request->input('session_id')
            ?? $request->input('sessionId')
            ?? $request->input('SessionId');

        $phone = $request->input('phone_number')
            ?? $request->input('phoneNumber')
            ?? $request->input('msisdn')
            ?? $request->input('MSISDN');

        $text = $request->input('text');
        if ($text === null) {
            $text = $request->input('userInput') ?? $request->input('input') ?? '';
        }

        $serviceCode = $request->input('service_code') ?? $request->input('serviceCode');

        validator([
            'session_id' => $sessionId,
            'phone_number' => $phone,
        ], [
            'session_id' => ['required', 'string', 'max:120'],
            'phone_number' => ['required', 'string', 'max:30'],
        ])->validate();

        return [
            'session_id' => (string) $sessionId,
            'phone_number' => (string) $phone,
            'text' => (string) $text,
            'service_code' => $serviceCode ? (string) $serviceCode : null,
        ];
    }

    /** @param  array<string, mixed>  $result */
    private function formatGatewayResponse(Request $request, array $result): JsonResponse|Response
    {
        if ($request->wantsPlainText() || $request->header('Accept') === 'text/plain') {
            return response($result['message'], 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if ($request->header('X-USSD-Response-Format') === 'africas_talking') {
            return response()->json([
                'response' => $result['plain_message'] ?? preg_replace('/^(CON|END)\s+/i', '', $result['message']),
                'close' => ! ($result['session_active'] ?? false),
            ]);
        }

        return response()->json($result);
    }

    /** @return array<string, mixed> */
    private function integrationMeta(): array
    {
        return [
            'short_code' => config('ussd.short_code'),
            'production_callback' => url('/api/v1/channels/ussd/callback'),
            'headers' => [
                'X-USSD-Secret' => 'Required when ussd_webhook_secret is set in system settings',
                'Accept' => 'application/json (default) or text/plain',
                'X-USSD-Response-Format' => 'Optional: africas_talking for { response, close } JSON',
            ],
            'telco_configuration' => [
                'point_short_code' => config('ussd.short_code'),
                'callback_url' => url('/api/v1/channels/ussd/callback'),
                'method' => 'POST',
                'map_fields' => 'session_id, phone_number, text — aliases documented in schema',
            ],
        ];
    }
}
