<?php

namespace App\Services\Ntb\Mobile;

use App\Models\Complaint;
use App\Models\MobilePushDevice;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MobilePushNotificationService
{
    public function registerDevice(User $user, string $fcmToken, string $platform, ?string $deviceName = null): MobilePushDevice
    {
        return MobilePushDevice::query()->updateOrCreate(
            ['user_id' => $user->id, 'fcm_token' => $fcmToken],
            [
                'platform' => $platform,
                'device_name' => $deviceName,
                'notify_case_updates' => true,
                'last_seen_at' => now(),
            ]
        );
    }

    public function unregisterDevice(User $user, string $fcmToken): void
    {
        MobilePushDevice::query()
            ->where('user_id', $user->id)
            ->where('fcm_token', $fcmToken)
            ->delete();
    }

    public function notifyReporterStatusChange(Complaint $complaint, string $fromStatus, string $toStatus): void
    {
        if (! $complaint->created_by) {
            return;
        }

        $statusLabel = $complaint->status?->name_en ?? $toStatus;
        $title = 'Case '.$complaint->complaint_code.' updated';
        $body = "Status: {$statusLabel}";

        $this->sendToUser((int) $complaint->created_by, $title, $body, [
            'type' => 'complaint_status_changed',
            'complaint_code' => $complaint->complaint_code,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
    }

    public function notifyReporterCaseSubmitted(Complaint $complaint): void
    {
        if (! $complaint->created_by) {
            return;
        }

        $this->sendToUser((int) $complaint->created_by, 'Report received', 'Your case '.$complaint->complaint_code.' was submitted.', [
            'type' => 'complaint_submitted',
            'complaint_code' => $complaint->complaint_code,
        ]);
    }

    /** @param  array<string, string>  $data */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $serverKey = config('ntb.fcm.server_key');
        if (! $serverKey) {
            return;
        }

        $tokens = MobilePushDevice::query()
            ->where('user_id', $userId)
            ->where('notify_case_updates', true)
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        foreach (array_chunk($tokens, 500) as $chunk) {
            $this->sendFcmLegacy($serverKey, $chunk, $title, $body, $data);
        }
    }

    /** @param  list<string>  $tokens  @param  array<string, string>  $data */
    private function sendFcmLegacy(string $serverKey, array $tokens, string $title, string $body, array $data): void
    {
        $payload = [
            'registration_ids' => $tokens,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => array_map('strval', $data),
            'priority' => 'high',
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post('https://fcm.googleapis.com/fcm/send', $payload);

            if (! $response->successful()) {
                Log::warning('FCM push failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM push exception', ['message' => $e->getMessage()]);
        }
    }
}
