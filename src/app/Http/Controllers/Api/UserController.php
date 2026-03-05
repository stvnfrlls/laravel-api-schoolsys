<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        //
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles:id,name,description');

        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
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
}
