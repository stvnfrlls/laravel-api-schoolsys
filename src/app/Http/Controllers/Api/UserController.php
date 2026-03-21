<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    public function index()
    {
        $users = User::with(['roles:id,name', 'student', 'teacher'])
            ->latest()
            ->paginate(20);

        return UserResource::collection($users);
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

            // Required when role is student
            'section_id' => ['required_if:role,student', 'nullable', 'exists:sections,id'],
            'grade_level_id' => ['required_if:role,student', 'nullable', 'exists:grade_levels,id'],
            'school_year' => ['required_if:role,student', 'nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required_if:role,student', 'nullable', Rule::in(['1st', '2nd', 'summer'])],
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
                $student = $user->student()->create([
                    'student_number' => 'STU-' . strtoupper(substr(md5($user->id . now()), 0, 8)),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'middle_name' => $data['middle_name'] ?? null,
                    'suffix' => $data['suffix'] ?? null,
                    'date_of_birth' => $data['date_of_birth'],
                    'gender' => $data['gender'],
                ]);

                $student->enrollments()->create([
                    'section_id' => $data['section_id'],
                    'grade_level_id' => $data['grade_level_id'],
                    'school_year' => $data['school_year'],
                    'semester' => $data['semester'],
                ]);

                $prefix = config('cache.prefix');
                \DB::table('cache')
                    ->where('key', 'like', $prefix . 'students.index.page.%')
                    ->delete();
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

                $prefix = config('cache.prefix');
                \DB::table('cache')
                    ->where('key', 'like', $prefix . 'teachers.index.page.%')
                    ->delete();
            }
        }

        $user->load([
            'roles:id,name',
            'student',
            'teacher',
        ]);

        return response()->json(new UserResource($user), 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles:id,name,description');

        return response()->json(new UserResource($user));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'    => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role'     => ['sometimes', 'integer', 'exists:roles,id'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        if (isset($data['role'])) {
            $user->roles()->sync([$data['role']]);
        }

        $user->update($data);

        return response()->json(new UserResource($user->load('roles:id,name')));
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

        return response()->json(['message' => 'User activated successfully.']);
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

        return response()->json(['message' => 'User deactivated successfully.']);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles:id,name');

        return response()->json(new UserResource($user));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:255'], // added 'nullable'
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);
        return response()->json(new UserResource($user->load('roles')));
    }
}
