<?php

namespace App\Services\Ntb\Uam;

use App\Models\NotificationTemplate;
use App\Models\NtbNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UamNotificationService
{
    public function notifyUser(User $user, string $eventType, array $context = []): void
    {
        $template = NotificationTemplate::query()
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            Log::info('UAM notification template missing', ['event' => $eventType, 'user_id' => $user->id]);

            return;
        }

        $locale = $context['locale'] ?? $user->preferred_language ?? 'fr';
        $subject = $locale === 'fr' ? ($template->subject_fr ?: $template->subject_en) : $template->subject_en;
        $body = $locale === 'fr' ? ($template->body_fr ?: $template->body_en) : $template->body_en;

        $replacements = array_merge([
            'user_name' => $user->displayName(),
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'role' => $user->getRoleNames()->first() ?? '',
            'institution' => $user->institution?->institution_name ?? '',
        ], $context);

        foreach ($replacements as $key => $value) {
            $subject = str_replace('{'.$key.'}', (string) $value, $subject ?? '');
            $body = str_replace('{'.$key.'}', (string) $value, $body ?? '');
        }

        $channels = $template->channels ?? ['email', 'portal'];

        foreach ($channels as $channel) {
            $recipient = $this->resolveRecipient($user, $channel, $context);
            if (! $recipient && $channel !== 'portal') {
                continue;
            }

            $notification = NtbNotification::query()->create([
                'complaint_id' => null,
                'user_id' => $user->id,
                'channel' => $channel,
                'recipient' => $recipient ?? (string) $user->id,
                'subject' => $subject,
                'message' => $body,
                'event_type' => $eventType,
                'delivery_status' => 'pending',
            ]);

            $this->deliver($notification);
        }
    }

    public function notifyAdmins(string $eventType, array $context = [], array $roleNames = ['system_administrator', 'mtt_admin']): void
    {
        $admins = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roleNames))
            ->get();

        foreach ($admins as $admin) {
            $this->notifyUser($admin, $eventType, $context);
        }
    }

    private function resolveRecipient(User $user, string $channel, array $context): ?string
    {
        if ($channel === 'sms') {
            return $user->phone_number ?: ($context['phone'] ?? null);
        }

        if ($channel === 'email') {
            return $user->email ?: ($context['email'] ?? null);
        }

        if ($channel === 'portal') {
            return (string) $user->id;
        }

        return null;
    }

    private function deliver(NtbNotification $notification): void
    {
        try {
            if ($notification->channel === 'email' && $notification->recipient) {
                Mail::raw($notification->message, function ($mail) use ($notification) {
                    $mail->to($notification->recipient)
                        ->subject($notification->subject ?? 'NTB Portal Notification');
                });
                $notification->update(['delivery_status' => 'sent', 'sent_at' => now()]);
            } elseif ($notification->channel === 'portal') {
                $notification->update(['delivery_status' => 'delivered', 'sent_at' => now()]);
            } else {
                Log::info('UAM notification logged', $notification->only(['event_type', 'channel', 'recipient']));
                $notification->update(['delivery_status' => 'logged', 'sent_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning('UAM notification failed', ['id' => $notification->id, 'error' => $e->getMessage()]);
            $notification->update(['delivery_status' => 'failed']);
        }
    }
}
