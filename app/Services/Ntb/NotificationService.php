<?php

namespace App\Services\Ntb;

use App\Models\Complaint;
use App\Models\NotificationTemplate;
use App\Models\NtbNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function dispatch(Complaint $complaint, string $eventType, array $context = []): void
    {
        $template = NotificationTemplate::query()
            ->where('event_type', $eventType)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return;
        }

        $locale = $context['locale'] ?? 'en';
        $subject = $locale === 'fr' ? ($template->subject_fr ?: $template->subject_en) : $template->subject_en;
        $body = $locale === 'fr' ? ($template->body_fr ?: $template->body_en) : $template->body_en;

        $replacements = array_merge([
            'case_code' => $complaint->complaint_code,
            'title' => $complaint->title,
            'status' => $complaint->status?->code,
        ], $context);

        foreach ($replacements as $key => $value) {
            $subject = str_replace('{'.$key.'}', (string) $value, $subject ?? '');
            $body = str_replace('{'.$key.'}', (string) $value, $body ?? '');
        }

        $channels = $template->channels ?? ['email'];

        foreach ($channels as $channel) {
            $recipient = $this->resolveRecipient($complaint, $channel, $context);

            if (! $recipient) {
                continue;
            }

            $notification = NtbNotification::query()->create([
                'complaint_id' => $complaint->id,
                'user_id' => $context['user_id'] ?? null,
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'message' => $body,
                'event_type' => $eventType,
                'delivery_status' => 'pending',
            ]);

            $this->send($notification);
        }
    }

    private function resolveRecipient(Complaint $complaint, string $channel, array $context): ?string
    {
        if ($channel === 'sms') {
            return $complaint->reporter_phone ?: ($context['phone'] ?? null);
        }

        if ($channel === 'email') {
            return $complaint->reporter_email ?: ($context['email'] ?? null);
        }

        return null;
    }

    private function send(NtbNotification $notification): void
    {
        try {
            if ($notification->channel === 'email' && $notification->recipient && config('mail.default')) {
                Mail::raw($notification->message, function ($mail) use ($notification) {
                    $mail->to($notification->recipient)
                        ->subject($notification->subject ?? 'NTB Portal Notification');
                });
                $notification->update(['delivery_status' => 'sent', 'sent_at' => now()]);
            } else {
                Log::info('NTB notification queued', $notification->only(['event_type', 'channel', 'recipient']));
                $notification->update(['delivery_status' => 'logged', 'sent_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::warning('NTB notification failed', ['id' => $notification->id, 'error' => $e->getMessage()]);
            $notification->update(['delivery_status' => 'failed']);
        }
    }
}
