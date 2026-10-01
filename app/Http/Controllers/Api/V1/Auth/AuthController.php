<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\Ntb\Uam\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly MfaService $mfa) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($credentials['email']);
        $user = User::query()
            ->where(fn ($q) => $q->where('email', $identifier)->orWhere('username', $identifier))
            ->first();

        if (str_contains($identifier, '@')) {
            $credentials['email'] = $identifier;
        } elseif ($user) {
            $credentials['email'] = $user->email;
        } else {
            $credentials['email'] = $identifier;
        }

        LoginAttempt::query()->create([
            'identifier' => $identifier,
            'user_id' => $user?->id,
            'successful' => false,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if ($user && in_array($user->status, ['locked', 'suspended', 'deactivated', 'archived'], true)) {
            throw ValidationException::withMessages([
                'email' => ['This account is not permitted to sign in.'],
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            if ($user) {
                $attempts = ($user->failed_login_attempts ?? 0) + 1;
                $updates = ['failed_login_attempts' => $attempts];
                if ($attempts >= 5) {
                    $updates['status'] = 'locked';
                    $updates['locked_at'] = now();
                }
                $user->update($updates);
                if ($attempts >= 5) {
                    app(\App\Services\Ntb\Uam\UamNotificationService::class)->notifyUser($user, 'NTF-UAM-009');
                }
            }

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        $authContext = strtolower((string) $request->header('X-Auth-Context', 'admin'));
        if ($authContext === 'admin' && ! $user->canAccessAdminPortal()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['This account is for the public reporting portal only. Use “Report a Barrier” to sign in.'],
            ]);
        }

        if ($authContext === 'portal' && ! $user->can('complaints.submit')) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => ['This account cannot submit complaints on the public portal. Use the administration login instead.'],
            ]);
        }

        if (! in_array($user->status, ['active', 'mfa_enrolment_required', 'password_reset_required'], true)) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['This account is not active.'],
            ]);
        }

        $user->load('institution');
        $user->update([
            'last_login_at' => now(),
            'failed_login_attempts' => 0,
            'locked_at' => null,
        ]);

        LoginAttempt::query()->where('identifier', $identifier)->latest()->limit(1)->update(['successful' => true]);

        if (! $this->mfa->isEnabled() && $user->status === 'mfa_enrolment_required') {
            $user->update(['status' => 'active']);
            $user->refresh();
        }

        if ($this->mfa->requiresMfa($user) && ! $this->mfa->isEnrolled($user)) {
            $user->update(['status' => 'mfa_enrolment_required']);
            $request->session()->put('mfa_verified', false);
            $request->session()->put('mfa_enrollment_required', true);

            return response()->json([
                'user' => $this->userPayload($user, false, true),
                'mfa_enrollment_required' => true,
            ]);
        }

        if ($this->mfa->requiresMfa($user) && $this->mfa->isEnrolled($user)) {
            $challenge = $this->mfa->createLoginChallenge($user);
            Auth::logout();
            $request->session()->put('mfa_pending_user_id', $user->id);
            $request->session()->put('mfa_challenge_id', $challenge['challenge_id']);
            $request->session()->put('mfa_verified', false);

            return response()->json([
                'mfa_required' => true,
                'challenge_id' => $challenge['challenge_id'],
                'channel' => $challenge['channel'],
                'expires_at' => $challenge['expires_at'],
                'email' => $user->email,
            ]);
        }

        $request->session()->put('mfa_verified', true);
        $request->session()->forget('mfa_enrollment_required');

        return response()->json([
            'user' => $this->userPayload($user, true, false),
        ]);
    }

    public function verifyMfa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $userId = $request->session()->get('mfa_pending_user_id');
        abort_unless($userId, 401, 'No pending MFA session.');

        $user = User::query()->findOrFail($userId);
        $this->mfa->verifyLoginChallenge($user, $data['challenge_id'], $data['code']);

        $authContext = strtolower((string) $request->header('X-Auth-Context', 'admin'));
        if ($authContext === 'admin' && ! $user->canAccessAdminPortal()) {
            throw ValidationException::withMessages([
                'code' => ['This account is not permitted to access the administration portal.'],
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('mfa_verified', true);
        $request->session()->forget(['mfa_pending_user_id', 'mfa_challenge_id']);

        $user->load('institution');

        return response()->json([
            'user' => $this->userPayload($user, true, false),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $user->load('institution');
        }

        $mfaVerified = (bool) $request->session()->get('mfa_verified', true);
        $enrollmentRequired = (bool) $request->session()->get('mfa_enrollment_required', false);

        return response()->json([
            'user' => $user ? $this->userPayload($user, $mfaVerified, $enrollmentRequired) : null,
            'authenticated' => (bool) $user,
            'mfa_enabled' => $this->mfa->isEnabled(),
        ]);
    }

    private function userPayload(User $user, bool $mfaVerified, bool $enrollmentRequired): array
    {
        if (! $this->mfa->isEnabled()) {
            $mfaVerified = true;
            $enrollmentRequired = false;
        }

        return [
            'id' => $user->id,
            'name' => $user->displayName(),
            'full_name' => $user->displayName(),
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'organization_name' => $user->organization_name,
            'country' => $user->country,
            'username' => $user->username,
            'preferred_language' => $user->preferred_language,
            'institution_id' => $user->institution_id,
            'institution' => $user->institution?->institution_name,
            'status' => $user->status,
            'account_category' => $user->account_category,
            'organization_name' => $user->organization_name,
            'country' => $user->country,
            'mfa_enrolled' => $user->mfa_enrolled,
            'mfa_verified' => $mfaVerified,
            'mfa_enrollment_required' => $this->mfa->isEnabled()
                && ($enrollmentRequired || $user->status === 'mfa_enrolment_required'),
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'can_access_admin_portal' => $user->canAccessAdminPortal(),
            'is_portal_reporter_only' => $user->isPortalReporterOnly(),
        ];
    }
}
