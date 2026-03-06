<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAddressController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json($user->address);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        if ($user->address()->exists()) {
            return response()->json([
                'message' => 'User already has an address. Use PUT to update it.',
            ], 409);
        }

        $address = $user->address()->create(
            $this->validatedData($request)
        );

        return response()->json($address, 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $address = $user->address()->updateOrCreate(
            ['user_id' => $user->id],
            $this->validatedData($request)
        );

        return response()->json($address->refresh());
    }

    public function destroy(User $user): JsonResponse
    {
        if (!$user->address()->exists()) {
            return response()->json([
                'message' => 'No address found for this user.',
            ], 404);
        }

        $user->address()->delete();

        return response()->json(['message' => 'Address removed successfully.']);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'street' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:10'],
        ]);
    }
}