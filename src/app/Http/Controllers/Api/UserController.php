<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    public function index(): JsonResponse
    {
        $users = User::with('roles:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'], // was 'required'
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $data['name'] ?? null, // was $data['name'] — would error if absent
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $roleName = $data['role'] ?? 'unassigned';
        $role = Role::where('name', $roleName)->first();

        if ($role) {
            $user->roles()->attach($role);
        }

        $user->load('roles:id,name');

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles:id,name,description');

        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'], // added 'nullable'
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);

        return response()->json($user->load('roles:id,name'));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    public function activate(User $user)
    {
        if ($user->is_active) {
            return response()->json([
                'message' => 'User is already active.'
            ], 422);
        }

        $user->update([
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'User activated successfully.'
        ]);
    }

    public function deactivate(User $user)
    {
        if (auth()->id() === $user->id) {
            return response()->json([
                'message' => 'You cannot deactivate yourself.'
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'User is already deactivated.'
            ], 422);
        }

        $user->update([
            'is_active' => false,
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'User deactivated successfully.'
        ], 200);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles:id,name');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->roles->pluck('name'),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'], // added 'nullable'
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->load('roles')->roles->pluck('name'),
        ]);
    }
}