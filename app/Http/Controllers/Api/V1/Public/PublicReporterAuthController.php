<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Ntb\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PublicReporterAuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

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
            'accept_terms' => ['accepted'],
        ]);

        if (! preg_match('/^\+?[0-9\s\-()]{7,30}$/', $data['phone_number'])) {
            return response()->json([
                'message' => 'Enter a valid telephone number.',
                'errors' => ['phone_number' => ['Enter a valid telephone number.']],
            ], 422);
        }

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

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('mfa_verified', true);

        $this->audit->log('reporter.register', $user, null, [
            'email' => $user->email,
            'account_category' => $user->account_category,
        ]);

        $user->load('roles');

        return response()->json([
            'message' => 'Registration successful',
            'user' => $this->userPayload($user),
        ], 201);
    }

    private function uniqueUsername(string $email, string $fullName): string
    {
        $base = Str::slug(Str::before($email, '@'), '_');
        if ($base === '') {
            $base = Str::slug($fullName, '_');
        }
        if ($base === '') {
            $base = 'reporter';
        }

        $candidate = Str::limit($base, 40, '');
        $suffix = 0;

        while (User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = Str::limit($base, 35, '').'_'.$suffix;
        }

        return $candidate;
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
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'can_access_admin_portal' => $user->canAccessAdminPortal(),
            'is_portal_reporter_only' => $user->isPortalReporterOnly(),
            'mfa_verified' => true,
            'mfa_enrollment_required' => false,
        ];
    }
}
