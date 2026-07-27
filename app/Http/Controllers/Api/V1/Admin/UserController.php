<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.users'), 403);

        $users = User::query()
            ->with(['institution', 'roles'])
            ->when($request->string('search')->toString(), function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(25);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.users'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'preferred_language' => ['nullable', 'in:en,fr'],
            'status' => ['nullable', 'string', 'max:30'],
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'full_name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'institution_id' => $data['institution_id'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? 'en',
            'status' => $data['status'] ?? 'active',
        ]);

        $user->syncRoles($data['roles']);

        return response()->json(['user' => $user->load(['institution', 'roles'])], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('admin.users'), 403);
        $user = User::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:100', 'unique:users,username,'.$id],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$id],
            'password' => ['nullable', 'string', 'min:8'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'preferred_language' => ['nullable', 'in:en,fr'],
            'status' => ['nullable', 'string', 'max:30'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update(collect($data)->except('roles')->all());

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles'] ?? []);
        }

        return response()->json(['user' => $user->fresh(['institution', 'roles'])]);
    }

    public function roles(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('admin.roles'), 403);

        return response()->json([
            'roles' => Role::query()->with('permissions')->orderBy('name')->get(),
            'institutions' => Institution::query()->orderBy('institution_name')->get(['id', 'institution_name']),
        ]);
    }
}
