<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{

    public function index()
    {
        $roles = Role::withCount('users')->get();

        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:roles,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::create($data);

        return response()->json($role, 201);
    }

    public function show(Role $role)
    {
        $role->load('users:id,name,email');

        return response()->json($role);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:50', Rule::unique('roles')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role->update($data);

        return response()->json($role);
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    public function assignToUser(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $roleIds = Role::whereIn('name', $request->roles)->pluck('id');
        $user->roles()->syncWithoutDetaching($roleIds);
        $user->load('roles');

        return response()->json([
            'message' => 'Roles assigned.',
            'roles' => $user->roles,
        ]);
    }

    public function removeFromUser(User $user, Role $role): JsonResponse
    {
        $user->roles()->detach($role);

        return response()->json(['message' => 'Role removed.']);
    }

    public function syncUserRoles(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $roleIds = Role::whereIn('name', $request->roles)->pluck('id');
        $user->roles()->sync($roleIds);
        $user->load('roles');

        return response()->json([
            'message' => 'Roles synced.',
            'roles' => $user->roles,
        ]);
    }
}
