<?php

namespace App\Services\Ntb;

use App\Models\SystemSetting;

class SystemSettingsService
{
    public function maxUploadKb(): int
    {
        return (int) SystemSetting::getValue('max_attachment_size_kb', 10240);
    }

    public function retentionYears(): int
    {
        return (int) SystemSetting::getValue('archive_retention_years', 7);
    }

    public function duplicateWindowDays(): int
    {
        return (int) SystemSetting::getValue('duplicate_detection_days', 14);
    }

    public function smsWebhookSecret(): ?string
    {
        return SystemSetting::getValue('sms_webhook_secret');
    }

    public function ussdWebhookSecret(): ?string
    {
        return SystemSetting::getValue('ussd_webhook_secret');
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return [
            'max_attachment_size_kb' => $this->maxUploadKb(),
            'archive_retention_years' => $this->retentionYears(),
            'duplicate_detection_days' => $this->duplicateWindowDays(),
            'sms_webhook_secret' => $this->smsWebhookSecret(),
            'ussd_webhook_secret' => $this->ussdWebhookSecret(),
        ];
    }
}
