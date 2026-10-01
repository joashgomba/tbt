<?php

namespace App\Services\Ntb\Ussd;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UssdVerificationService
{
    public function msisdnMatchesComplaint(string $msisdn, Complaint $complaint): bool
    {
        $normalized = $this->normalizeMsisdn($msisdn);

        if ($complaint->reporter_phone && $this->phonesMatch($normalized, $complaint->reporter_phone)) {
            return true;
        }

        $creator = $complaint->creator;
        if ($creator?->phone_number && $this->phonesMatch($normalized, $creator->phone_number)) {
            return true;
        }

        if ($complaint->submission_channel === 'USSD' && $creator && $this->phonesMatch($normalized, (string) ($creator->phone_number ?? ''))) {
            return true;
        }

        return false;
    }

    public function issueTrackOtp(array &$payload): array
    {
        $code = (string) random_int(100000, 999999);
        $payload['otp_hash'] = Hash::make($code);
        $payload['otp_expires_at'] = now()->addMinutes((int) config('ussd.otp_ttl_minutes', 10))->toIso8601String();
        $payload['otp_purpose'] = 'track';

        Log::info('USSD track OTP issued', ['code' => $code, 'purpose' => 'track']);

        return ['code' => $code];
    }

    public function verifyOtp(array &$payload, string $input): bool
    {
        $expires = $payload['otp_expires_at'] ?? null;
        if (! $expires || now()->greaterThan($expires)) {
            return false;
        }

        $hash = $payload['otp_hash'] ?? null;
        if (! $hash || ! Hash::check($input, $hash)) {
            return false;
        }

        unset($payload['otp_hash'], $payload['otp_expires_at'], $payload['otp_purpose']);

        return true;
    }

    public function verifyUserPin(?User $user, string $input): bool
    {
        return $user && $user->ussd_pin_hash && Hash::check($input, $user->ussd_pin_hash);
    }

    public function normalizeMsisdn(string $msisdn): string
    {
        $digits = preg_replace('/\D+/', '', $msisdn) ?? '';

        return $digits;
    }

    public function phonesMatch(string $a, string $b): bool
    {
        $na = $this->normalizeMsisdn($a);
        $nb = $this->normalizeMsisdn($b);
        if ($na === '' || $nb === '') {
            return false;
        }

        if ($na === $nb) {
            return true;
        }

        return str_ends_with($na, substr($nb, -8)) || str_ends_with($nb, substr($na, -8));
    }

    public function resolveUserByPhone(string $msisdn): ?User
    {
        $normalized = $this->normalizeMsisdn($msisdn);
        $users = User::query()
            ->where('status', 'active')
            ->whereNotNull('phone_number')
            ->get()
            ->filter(fn (User $u) => $this->phonesMatch($normalized, $u->phone_number))
            ->filter(fn (User $u) => $u->can('complaints.submit') || $u->canAccessAdminPortal());

        return $users->first();
    }
}
