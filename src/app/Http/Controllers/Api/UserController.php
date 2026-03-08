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
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['sometimes', 'string', 'exists:roles,name'],

            // Required when role is student or faculty
            'first_name' => ['required_if:role,student,faculty', 'nullable', 'string', 'max:255'],
            'last_name' => ['required_if:role,student,faculty', 'nullable', 'string', 'max:255'],
            'date_of_birth' => ['required_if:role,student,faculty', 'nullable', 'date'],
            'gender' => ['required_if:role,student,faculty', 'nullable', 'in:male,female,other'],

            // Optional for both
            'middle_name' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],

            // Faculty only
            'specialization' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $data['name'] ?? null,
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $roleName = $data['role'] ?? 'unassigned';
        $role = Role::where('name', $roleName)->first();

        if ($role) {
            $user->roles()->attach($role);

            if ($roleName === 'student') {
                $user->student()->create([
                    'student_number' => 'STU-' . strtoupper(substr(md5($user->id . now()), 0, 8)),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'suffix' => $data['suffix'] ?? null,
                    'date_of_birth' => $data['date_of_birth'],
                    'gender' => $data['gender'],
                ]);
            }

            if ($roleName === 'faculty') {
                $user->teacher()->create([
                    'employee_number' => 'EMP-' . strtoupper(substr(md5($user->id . now()), 0, 8)),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'suffix' => $data['suffix'] ?? null,
                    'date_of_birth' => $data['date_of_birth'],
                    'gender' => $data['gender'],
                    'specialization' => $data['specialization'] ?? null,
                ]);
            }
        }

        $user->load([
            'roles:id,name',
            'student',
            'teacher',
        ]);

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