<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ntb\Mobile\MobilePushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobilePushController extends Controller
{
    public function __construct(private readonly MobilePushNotificationService $push) {}

    public function registerDevice(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:20'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $device = $this->push->registerDevice(
            $user,
            $data['fcm_token'],
            $data['platform'] ?? 'unknown',
            $data['device_name'] ?? null,
        );

        return response()->json(['device' => $device]);
    }

    public function unregisterDevice(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
        ]);

        $this->push->unregisterDevice($user, $data['fcm_token']);

        return response()->json(['message' => 'Device unregistered']);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
            'notify_case_updates' => ['required', 'boolean'],
        ]);

        $device = $user->mobilePushDevices()
            ->where('fcm_token', $data['fcm_token'])
            ->firstOrFail();

        $device->update(['notify_case_updates' => $data['notify_case_updates']]);

        return response()->json(['device' => $device->fresh()]);
    }
}
