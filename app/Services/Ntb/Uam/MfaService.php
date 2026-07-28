<?php

namespace App\Services\Ntb\Uam;

use App\Models\MfaChallenge;
use App\Models\MfaMethod;
use App\Models\User;
use App\Services\Ntb\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MfaService
{
    private const MANDATORY_ROLES = [
        'system_administrator',
        'mtt_admin',
        'institutional_administrator',
        'national_focal_point',
    ];

    public function __construct(
        private readonly UamNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function requiresMfa(User $user): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return $user->hasRole(self::MANDATORY_ROLES)
            || $user->status === 'mfa_enrolment_required';
    }

    public function isEnabled(): bool
    {
        return (bool) config('ntb.mfa_enabled', false);
    }

    public function isEnrolled(User $user): bool
    {
        return $user->mfa_enrolled
            || $user->mfaMethods()->where('is_verified', true)->exists();
    }

    public function status(User $user): array
    {
        $methods = $user->mfaMethods()->orderByDesc('is_primary')->get();

        return [
            'enabled' => $this->isEnabled(),
            'required' => $this->requiresMfa($user),
            'enrolled' => $this->isEnrolled($user),
            'methods' => $methods->map(fn (MfaMethod $m) => [
                'id' => $m->id,
                'method' => $m->method,
                'destination' => $this->maskDestination($m->destination),
                'is_primary' => $m->is_primary,
                'is_verified' => $m->is_verified,
            ]),
        ];
    }

    public function startEnrollment(User $user, string $method): array
    {
        abort_unless(in_array($method, ['email', 'sms'], true), 422, 'Unsupported MFA method.');

        $destination = $method === 'email' ? $user->email : $user->phone_number;
        abort_if(! $destination, 422, 'No verified contact available for this method.');

        $mfaMethod = MfaMethod::query()->updateOrCreate(
            ['user_id' => $user->id, 'method' => $method],
            [
                'destination' => $destination,
                'is_verified' => false,
                'verified_at' => null,
            ],
        );

        $challenge = $this->issueChallenge($user, 'enrollment', $method);

        $this->notifications->notifyUser($user, 'NTF-UAM-002', [
            'otp_code' => $challenge['code'],
            'method' => $method,
        ]);

        $this->audit->log('uam.mfa.enroll_start', $user, null, ['method' => $method]);

        return [
            'method_id' => $mfaMethod->id,
            'challenge_id' => $challenge['id'],
            'expires_at' => $challenge['expires_at'],
        ];
    }

    public function verifyEnrollment(User $user, int $challengeId, string $code): MfaMethod
    {
        $this->assertChallenge($user, $challengeId, $code, 'enrollment');

        $method = $user->mfaMethods()->where('is_verified', false)->latest()->firstOrFail();
        $method->update([
            'is_verified' => true,
            'verified_at' => now(),
            'is_primary' => ! $user->mfaMethods()->where('is_verified', true)->where('id', '!=', $method->id)->exists(),
        ]);

        $user->update([
            'mfa_enrolled' => true,
            'status' => $user->status === 'mfa_enrolment_required' ? 'active' : $user->status,
        ]);

        $this->notifications->notifyUser($user, 'NTF-UAM-012');
        $this->audit->log('uam.mfa.enroll_complete', $user, null, ['method' => $method->method]);

        return $method->fresh();
    }

    public function createLoginChallenge(User $user): array
    {
        $primary = $user->mfaMethods()->where('is_verified', true)->orderByDesc('is_primary')->first();
        $channel = $primary?->method ?? 'email';
        $challenge = $this->issueChallenge($user, 'login', $channel);

        $this->notifications->notifyUser($user, 'NTF-UAM-002', [
            'otp_code' => $challenge['code'],
            'method' => $channel,
        ]);

        return [
            'challenge_id' => $challenge['id'],
            'channel' => $channel,
            'expires_at' => $challenge['expires_at'],
        ];
    }

    public function verifyLoginChallenge(User $user, int $challengeId, string $code): void
    {
        $this->assertChallenge($user, $challengeId, $code, 'login');
        $this->audit->log('uam.mfa.login_verified', $user);
    }

    public function resetForUser(User $user, User $actor): void
    {
        $user->mfaMethods()->delete();
        $user->update([
            'mfa_enrolled' => false,
            'status' => $this->requiresMfa($user) ? 'mfa_enrolment_required' : $user->status,
        ]);

        $this->notifications->notifyUser($user, 'NTF-UAM-013');
        $this->notifications->notifyAdmins('NTF-UAM-013', [
            'user_name' => $user->displayName(),
            'actor' => $actor->displayName(),
        ]);

        $this->audit->log('uam.mfa.reset', $user, null, ['reset_by' => $actor->id]);
    }

    private function issueChallenge(User $user, string $purpose, string $channel): array
    {
        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        $challenge = MfaChallenge::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'channel' => $channel,
            'expires_at' => $expiresAt,
        ]);

        return [
            'id' => $challenge->id,
            'code' => $code,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    private function assertChallenge(User $user, int $challengeId, string $code, string $purpose): void
    {
        $challenge = MfaChallenge::query()
            ->where('user_id', $user->id)
            ->where('id', $challengeId)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->firstOrFail();

        abort_if($challenge->expires_at->isPast(), 422, 'Verification code has expired.');
        abort_if($challenge->attempts >= 5, 422, 'Too many failed attempts.');

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');
            abort(422, 'Invalid verification code.');
        }

        $challenge->update(['consumed_at' => now()]);
    }

    private function maskDestination(?string $destination): ?string
    {
        if (! $destination) {
            return null;
        }

        if (str_contains($destination, '@')) {
            [$local, $domain] = explode('@', $destination, 2);

            return Str::substr($local, 0, 2).'***@'.$domain;
        }

        return '***'.Str::substr($destination, -4);
    }
}
