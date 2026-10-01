<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccessReview;
use App\Models\Delegation;
use App\Models\Institution;
use App\Models\InstitutionUnit;
use App\Models\LoginAttempt;
use App\Models\NtbNotification;
use App\Models\User;
use App\Services\Ntb\AuditLogger;
use App\Services\Ntb\Uam\AccessReviewService;
use App\Services\Ntb\Uam\MfaService;
use App\Services\Ntb\Uam\UamNotificationService;
use App\Services\Ntb\Uam\UserAccessScopeService;
use App\Services\Ntb\Uam\UserLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserAccessController extends Controller
{
    private const STATUSES = [
        'pending_verification',
        'pending_approval',
        'active',
        'locked',
        'suspended',
        'inactive',
        'deactivated',
        'password_reset_required',
        'mfa_enrolment_required',
        'archived',
    ];

    private const CATEGORIES = [
        'external',
        'institutional',
        'technical',
        'national',
        'regional',
        'service',
    ];

    public function __construct(
        private readonly UserAccessScopeService $scope,
        private readonly UserLifecycleService $lifecycle,
        private readonly AccessReviewService $accessReviews,
        private readonly MfaService $mfa,
        private readonly UamNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        $userQuery = $this->scope->scopedUsersQuery($request);
        $privilegedRoles = ['system_administrator', 'mtt_admin', 'institutional_administrator', 'national_focal_point'];

        $mandatoryMfaQuery = (clone $userQuery)
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $privilegedRoles));

        $mfaRequired = (clone $mandatoryMfaQuery)->count();
        $mfaEnrolled = (clone $mandatoryMfaQuery)->where('mfa_enrolled', true)->count();
        $dormantThreshold = now()->subDays(90);

        return response()->json([
            'summary' => [
                'total_users' => (clone $userQuery)->count(),
                'active_users' => (clone $userQuery)->where('status', 'active')->count(),
                'pending_users' => (clone $userQuery)->whereIn('status', ['pending_verification', 'pending_approval'])->count(),
                'suspended_locked' => (clone $userQuery)->whereIn('status', ['suspended', 'locked'])->count(),
                'dormant_accounts' => (clone $userQuery)
                    ->where('status', 'active')
                    ->where(function ($q) use ($dormantThreshold) {
                        $q->whereNull('last_login_at')->orWhere('last_login_at', '<', $dormantThreshold);
                    })
                    ->count(),
                'mfa_compliance_pct' => $mfaRequired > 0 ? (int) round(($mfaEnrolled / $mfaRequired) * 100) : 100,
                'active_delegations' => Delegation::query()
                    ->where('status', 'active')
                    ->where('ends_at', '>', now())
                    ->count(),
                'roles_count' => Role::query()
                    ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
                    ->count(),
            ],
            'leaderboard' => $this->buildLeaderboard($request),
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        $institutionQuery = Institution::query()->orderBy('institution_name');
        $actor = $request->user();

        if ($actor->hasRole('institutional_administrator') && $actor->institution_id && ! $actor->can('admin.roles')) {
            $institutionQuery->where('id', $actor->institution_id);
        }

        $roleQuery = Role::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->orderBy('name');

        if ($actor->hasRole('national_focal_point') && ! $actor->hasRole(['system_administrator', 'mtt_admin'])) {
            $roleQuery->whereIn('name', UserAccessScopeService::NATIONAL_FOCAL_ASSIGNABLE_ROLES);
        }

        return response()->json([
            'institutions' => $institutionQuery->get(['id', 'institution_name', 'institution_code', 'status']),
            'roles' => $roleQuery->get(['id', 'name', 'code', 'title_en', 'title_fr', 'category', 'scope']),
            'statuses' => self::STATUSES,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        $query = $this->scope->scopedUsersQuery($request)
            ->with(['institution:id,institution_name', 'roles:id,name,code'])
            ->orderByDesc('created_at');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('institution_id')) {
            $query->where('institution_id', $request->integer('institution_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('role')) {
            $role = $request->string('role');
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($request->filled('account_category')) {
            $query->where('account_category', $request->string('account_category'));
        }

        return response()->json(['data' => $query->limit(200)->get()]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', 'max:20'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'unit_id' => ['nullable', 'exists:institution_units,id'],
            'preferred_language' => ['nullable', Rule::in(['fr', 'en'])],
            'account_category' => ['nullable', Rule::in(self::CATEGORIES)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.0' => ['required', 'string', 'exists:roles,name'],
            'role_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $this->lifecycle->create($request, $data);

        return response()->json($user, 201);
    }

    public function showUser(Request $request, int $id): JsonResponse
    {
        $user = $this->scopedUser($request, $id);
        abort_unless($this->scope->canManageUser($request, $user) || $request->user()?->id === $user->id, 403);

        $user->load(['institution', 'unit', 'roles', 'roleAssignments.role']);

        return response()->json([
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'role_history' => $user->roleAssignments,
        ]);
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = $this->scopedUser($request, $id);
        abort_unless($this->scope->canManageUser($request, $user), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'full_name' => ['sometimes', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', 'max:20'],
            'preferred_language' => ['sometimes', Rule::in(['fr', 'en'])],
            'job_title' => ['nullable', 'string', 'max:120'],
            'is_focal_point' => ['sometimes', 'boolean'],
            'is_institution_manager' => ['sometimes', 'boolean'],
            'reason' => ['required_with:institution_id,unit_id', 'nullable', 'string', 'max:500'],
        ]);

        $old = $user->only(array_keys($data));
        unset($data['reason']);
        $user->update($data);
        $this->audit->log('uam.user.update', $user, $old, $user->fresh()->only(array_keys($old)), $request);

        return response()->json($user->fresh(['institution', 'roles']));
    }

    public function activateUser(Request $request, int $id): JsonResponse
    {
        $user = $this->scopedUser($request, $id);
        $reason = $request->string('reason')->toString() ?: null;

        return response()->json($this->lifecycle->activate($request, $user, $reason));
    }

    public function suspendUser(Request $request, int $id): JsonResponse
    {
        $user = $this->scopedUser($request, $id);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'until' => ['nullable', 'date'],
        ]);

        return response()->json($this->lifecycle->suspend($request, $user, $data['reason'], $data['until'] ?? null));
    }

    public function deactivateUser(Request $request, int $id): JsonResponse
    {
        $user = $this->scopedUser($request, $id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return response()->json($this->lifecycle->deactivate($request, $user, $data['reason']));
    }

    public function unlockUser(Request $request, int $id): JsonResponse
    {
        $user = $this->scopedUser($request, $id);

        return response()->json($this->lifecycle->unlock($request, $user));
    }

    public function assignRole(Request $request, int $id): JsonResponse
    {
        $user = $this->scopedUser($request, $id);
        $data = $request->validate([
            'role' => ['required', 'string', 'exists:roles,name'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        return response()->json($this->lifecycle->assignRole($request, $user, $data['role'], $data['reason']));
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $user = $this->scopedUser($request, $id);
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);

        return response()->json($this->lifecycle->resetPassword($request, $user, $data['password']));
    }

    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);

        $rolesTable = config('permission.table_names.roles');
        $modelHasRoles = config('permission.table_names.model_has_roles');

        $roles = Role::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) use ($modelHasRoles) {
                $role->users_count = DB::table($modelHasRoles)
                    ->where('role_id', $role->id)
                    ->where('model_type', User::class)
                    ->count();

                return $role;
            });

        return response()->json(['roles' => $roles]);
    }

    public function showRole(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);

        $role = Role::query()->with('permissions:id,name,resource,action,data_scope,title_en,title_fr')->findOrFail($id);

        return response()->json(['role' => $role]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:roles,name'],
            'code' => ['required', 'string', 'max:80', 'unique:roles,code'],
            'title_en' => ['required', 'string', 'max:120'],
            'title_fr' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:50'],
            'scope' => ['required', 'string', 'max:50'],
            'description_en' => ['nullable', 'string'],
            'description_fr' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->update(collect($data)->except(['name', 'permissions'])->all());

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        $this->audit->log('uam.role.create', $role, null, $role->toArray(), $request);

        return response()->json($role->load('permissions'), 201);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);

        $role = Role::query()->findOrFail($id);
        abort_if($role->is_protected && ! $request->user()?->hasRole('system_administrator'), 422, 'This role is protected.');

        $data = $request->validate([
            'title_en' => ['sometimes', 'string', 'max:120'],
            'title_fr' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', 'string', 'max:50'],
            'scope' => ['sometimes', 'string', 'max:50'],
            'description_en' => ['nullable', 'string'],
            'description_fr' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $old = $role->only(array_keys($data));
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        $role->update($data);
        $role->increment('version');

        if ($permissions !== null) {
            $role->syncPermissions($permissions);
        }

        $this->audit->log('uam.role.update', $role, $old, $role->fresh()->only(array_keys($old)), $request);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json($role->fresh()->load('permissions'));
    }

    public function permissions(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);

        return response()->json([
            'permissions' => Permission::query()->orderBy('name')->get(),
        ]);
    }

    public function permissionMatrix(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);

        $roles = Role::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->orderBy('name')
            ->with('permissions:id,name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'permissions' => $role->permissions->pluck('name')->all(),
            ]);

        $permissions = Permission::query()->orderBy('name')->pluck('name');

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function institutions(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        $query = Institution::query()->withCount([
            'users as active_users_count' => fn ($q) => $q->where('status', 'active'),
        ])->orderBy('institution_name');

        $actor = $request->user();
        if ($actor->hasRole('institutional_administrator') && $actor->institution_id && ! $actor->can('admin.institutions')) {
            $query->where('id', $actor->institution_id);
        }

        return response()->json(['institutions' => $query->get()]);
    }

    public function storeInstitution(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.institutions'), 403);

        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_fr' => ['nullable', 'string', 'max:255'],
            'institution_code' => ['required', 'string', 'max:30', 'unique:institutions,institution_code'],
            'institution_type' => ['required', 'string', 'max:80'],
            'country_code' => ['required', 'string', 'size:2'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'suspended', 'archived'])],
        ]);

        $institution = Institution::query()->create([
            ...$data,
            'name_en' => $data['name_en'] ?? $data['institution_name'],
            'name_fr' => $data['name_fr'] ?? $data['institution_name'],
            'status' => $data['status'] ?? 'active',
        ]);

        $this->audit->log('uam.institution.create', $institution, null, $institution->toArray(), $request);

        return response()->json($institution, 201);
    }

    public function updateInstitution(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.institutions'), 403);

        $institution = Institution::query()->findOrFail($id);
        $data = $request->validate([
            'institution_name' => ['sometimes', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'name_fr' => ['nullable', 'string', 'max:255'],
            'institution_type' => ['sometimes', 'string', 'max:80'],
            'contact_email' => ['nullable', 'email'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'mandate' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended', 'archived'])],
            'reason' => ['required_with:status', 'nullable', 'string', 'max:500'],
        ]);

        $old = $institution->only(array_keys($data));
        unset($data['reason']);
        $institution->update($data);
        $this->audit->log('uam.institution.update', $institution, $old, $institution->fresh()->only(array_keys($old)), $request);

        return response()->json($institution->fresh());
    }

    public function institutionUsers(Request $request, int $id): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);
        abort_unless($this->scope->canAccessInstitution($request, $id), 403);

        $users = User::query()
            ->where('institution_id', $id)
            ->with('roles:id,name')
            ->orderBy('full_name')
            ->get();

        return response()->json(['users' => $users]);
    }

    public function portalReporters(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);
        abort_unless(
            $request->user()?->can('admin.users') && $request->user()?->hasRole(['system_administrator', 'mtt_admin', 'national_focal_point']),
            403,
            'Only national administrators can moderate public complaint reporters.'
        );

        $query = $this->scope->portalReportersQuery()
            ->with(['roles:id,name'])
            ->orderByDesc('created_at');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('organization_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->limit(200)->get(),
        ]);
    }

    public function units(Request $request, int $id): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        if ($request->isMethod('post')) {
            abort_unless($request->user()?->can('admin.institutions') || $request->user()?->hasRole('institutional_administrator'), 403);

            $data = $request->validate([
                'unit_code' => ['required', 'string', 'max:50'],
                'name_en' => ['required', 'string', 'max:255'],
                'name_fr' => ['required', 'string', 'max:255'],
                'parent_id' => ['nullable', 'exists:institution_units,id'],
                'manager_id' => ['nullable', 'exists:users,id'],
            ]);

            $unit = InstitutionUnit::query()->create([
                ...$data,
                'institution_id' => $id,
            ]);

            $this->audit->log('uam.unit.create', $unit, null, $unit->toArray(), $request);

            return response()->json($unit, 201);
        }

        $units = InstitutionUnit::query()
            ->where('institution_id', $id)
            ->orderBy('name_en')
            ->get();

        return response()->json(['units' => $units]);
    }

    public function delegations(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        if ($request->isMethod('post')) {
            $data = $request->validate([
                'delegator_id' => ['required', 'exists:users,id'],
                'delegate_id' => ['required', 'exists:users,id', 'different:delegator_id'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['required', 'date', 'after:starts_at'],
                'reason' => ['required', 'string', 'max:500'],
                'permissions' => ['nullable', 'array'],
            ]);

            $delegator = User::query()->findOrFail($data['delegator_id']);
            abort_unless($this->scope->canManageUser($request, $delegator) || $request->user()?->id === $delegator->id, 403);

            if ($delegator->institution_id) {
                $delegate = User::query()->findOrFail($data['delegate_id']);
                abort_if($delegate->institution_id !== $delegator->institution_id, 422, 'Delegation must stay within the same institution.');
            }

            $delegation = Delegation::query()->create([
                ...$data,
                'status' => 'active',
                'approved_by' => $request->user()?->id,
            ]);

            $this->audit->log('uam.delegation.create', $delegation, null, $delegation->toArray(), $request);
            $delegation->load(['delegator', 'delegate']);
            $this->notifications->notifyUser($delegation->delegator, 'NTF-UAM-014', [
                'delegate_name' => $delegation->delegate?->displayName(),
            ]);
            $this->notifications->notifyUser($delegation->delegate, 'NTF-UAM-014', [
                'delegator_name' => $delegation->delegator?->displayName(),
            ]);

            return response()->json($delegation, 201);
        }

        $delegations = Delegation::query()
            ->with(['delegator:id,name,full_name', 'delegate:id,name,full_name'])
            ->orderByDesc('starts_at')
            ->limit(100)
            ->get();

        return response()->json(['delegations' => $delegations]);
    }

    public function revokeDelegation(Request $request, int $id): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        $delegation = Delegation::query()->findOrFail($id);
        $delegation->update(['status' => 'revoked', 'ends_at' => now()]);
        $this->audit->log('uam.delegation.revoke', $delegation, null, ['status' => 'revoked'], $request);

        return response()->json($delegation->fresh());
    }

    public function accessReviews(Request $request): JsonResponse
    {
        abort_unless($this->scope->canManageUsers($request), 403);

        $query = AccessReview::query()->with('institution:id,institution_name')->orderByDesc('due_at');

        if ($request->user()->hasRole('institutional_administrator') && $request->user()->institution_id) {
            $query->where('institution_id', $request->user()->institution_id);
        }

        return response()->json(['reviews' => $query->limit(50)->get()]);
    }

    public function storeAccessReview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'due_at' => ['nullable', 'date'],
            'reviewer_id' => ['nullable', 'exists:users,id'],
        ]);

        $review = $this->accessReviews->create($request, $data);

        return response()->json($review, 201);
    }

    public function showAccessReview(Request $request, int $id): JsonResponse
    {
        $review = AccessReview::query()->findOrFail($id);

        return response()->json($this->accessReviews->show($request, $review));
    }

    public function certifyAccessReview(Request $request, int $id): JsonResponse
    {
        $review = AccessReview::query()->findOrFail($id);
        $data = $request->validate([
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.user_id' => ['required', 'exists:users,id'],
            'decisions.*.decision' => ['required', 'in:confirmed,role_changed,access_revoked,flagged'],
            'decisions.*.new_role_name' => ['nullable', 'string', 'exists:roles,name'],
            'decisions.*.notes' => ['nullable', 'string', 'max:500'],
            'complete' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->accessReviews->certify($request, $review, $data['decisions'], $data['complete'] ?? false);

        return response()->json($updated);
    }

    public function resetUserMfa(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);
        $user = $this->scopedUser($request, $id);
        $this->mfa->resetForUser($user, $request->user());

        return response()->json(['message' => 'MFA reset', 'user' => $user->fresh()]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $items = NtbNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['notifications' => $items]);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        $notification = NtbNotification::query()
            ->where('user_id', $request->user()?->id)
            ->findOrFail($id);

        $notification->update(['read_at' => now()]);

        return response()->json($notification);
    }

    public function loginAttempts(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles') || $request->user()?->hasRole('system_administrator'), 403);

        $attempts = LoginAttempt::query()
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'identifier', 'user_id', 'successful', 'ip_address', 'created_at']);

        return response()->json(['attempts' => $attempts]);
    }

    public function privilegedReport(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);

        $privilegedRoles = ['system_administrator', 'mtt_admin', 'institutional_administrator', 'national_focal_point'];

        $users = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $privilegedRoles))
            ->with(['institution:id,institution_name', 'roles:id,name'])
            ->orderBy('full_name')
            ->get();

        return response()->json(['users' => $users]);
    }

    private function scopedUser(Request $request, int $id): User
    {
        return $this->scope->scopedUsersQuery($request)->findOrFail($id);
    }

    private function buildLeaderboard(Request $request): array
    {
        $institutionQuery = Institution::query()->orderBy('institution_name');
        $actor = $request->user();

        if ($actor->hasRole('institutional_administrator') && $actor->institution_id && ! $actor->can('admin.roles')) {
            $institutionQuery->where('id', $actor->institution_id);
        }

        $institutions = $institutionQuery->get();
        $rows = [];

        foreach ($institutions as $institution) {
            $activeUsers = User::query()
                ->where('institution_id', $institution->id)
                ->where('status', 'active')
                ->count();

            $focalPoints = User::query()
                ->where('institution_id', $institution->id)
                ->where('is_focal_point', true)
                ->where('status', 'active')
                ->count();

            $mandatoryMfa = User::query()
                ->where('institution_id', $institution->id)
                ->where('status', 'active')
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                    'system_administrator', 'mtt_admin', 'institutional_administrator', 'national_focal_point',
                ]))
                ->count();

            $mfaEnrolled = User::query()
                ->where('institution_id', $institution->id)
                ->where('status', 'active')
                ->where('mfa_enrolled', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', [
                    'system_administrator', 'mtt_admin', 'institutional_administrator', 'national_focal_point',
                ]))
                ->count();

            $openCases = DB::table('complaints')
                ->where('assigned_institution_id', $institution->id)
                ->whereNull('closed_at')
                ->count();

            $rows[] = [
                'institution' => $institution,
                'active_users' => $activeUsers,
                'focal_points' => $focalPoints,
                'mfa_pct' => $mandatoryMfa > 0 ? (int) round(($mfaEnrolled / $mandatoryMfa) * 100) : 100,
                'open_cases' => $openCases,
            ];
        }

        usort($rows, fn ($a, $b) => $b['active_users'] <=> $a['active_users']);

        return $rows;
    }
}
