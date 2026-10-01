<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileApiToken;
use App\Models\User;
use App\Services\Ntb\Uam\MfaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class MobileAuthController extends Controller
{
    public function __construct(private readonly MfaService $mfa) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user && in_array($user->status, ['locked', 'suspended', 'deactivated', 'archived'], true)) {
            throw ValidationException::withMessages([
                'email' => ['This account is not permitted to sign in.'],
            ]);
        }

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();
        Auth::logout();

        abort_unless($user->can('complaints.submit'), 403, 'This account cannot submit complaints via the mobile app.');

        if ($this->mfa->requiresMfa($user) && $this->mfa->isEnabled()) {
            throw ValidationException::withMessages([
                'email' => ['Multi-factor authentication must be completed on the web portal before using the mobile app.'],
            ]);
        }

        $issued = MobileApiToken::issue($user, $credentials['device_name']);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issued['plain_text'],
            'expires_at' => $issued['token']->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user),
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone_number' => ['required', 'string', 'max:30', 'unique:users,phone_number'],
            'organization_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', 'in:M,F,NB,NA'],
            'preferred_language' => ['nullable', 'in:en,fr'],
            'device_name' => ['required', 'string', 'max:120'],
            'accept_terms' => ['accepted'],
        ]);

        $username = $this->uniqueUsername($data['email'], $data['full_name']);

        $user = User::query()->create([
            'name' => $data['full_name'],
            'full_name' => $data['full_name'],
            'username' => $username,
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'gender' => $data['gender'] ?? null,
            'password' => Hash::make($data['password']),
            'organization_name' => $data['organization_name'],
            'country' => $data['country'],
            'preferred_language' => $data['preferred_language'] ?? 'en',
            'account_category' => 'external',
            'status' => 'active',
            'terms_accepted_at' => now(),
            'password_must_change' => false,
        ]);

        $user->assignRole('economic_operator');

        $issued = MobileApiToken::issue($user, $data['device_name']);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $issued['plain_text'],
            'expires_at' => $issued['token']->expires_at?->toIso8601String(),
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var MobileApiToken|null $token */
        $token = $request->attributes->get('mobile_api_token');
        $token?->delete();

        return response()->json(['message' => 'Logged out']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->displayName(),
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'organization_name' => $user->organization_name,
            'country' => $user->country,
            'preferred_language' => $user->preferred_language,
            'account_category' => $user->account_category,
            'status' => $user->status,
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }

    private function uniqueUsername(string $email, string $fullName): string
    {
        $base = Str::slug(Str::before($email, '@'), '_') ?: Str::slug($fullName, '_') ?: 'reporter';
        $candidate = Str::limit($base, 40, '');
        $suffix = 0;
        while (User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = Str::limit($base, 35, '').'_'.$suffix;
        }

        return $candidate;
    }
}
