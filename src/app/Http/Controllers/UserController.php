<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(): JsonResponse
    {
        $users = User::where('company_id', auth()->user()->company_id)
            ->latest()
            ->paginate(15);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $temporaryPassword = Str::random(12);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $temporaryPassword,
        ]);
        $user->role = $data['role'];
        $user->company_id = auth()->user()->company_id;
        $user->save();

        return response()->json([
            ...$user->toArray(),
            'temporary_password' => $temporaryPassword,
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('role', $data) && auth()->user()->role !== 'owner') {
            throw ValidationException::withMessages([
                'role' => ['Only an owner can change roles.'],
            ]);
        }

        if (
            $user->id === auth()->id()
            && array_key_exists('role', $data)
            && $data['role'] !== 'owner'
            && $user->role === 'owner'
        ) {
            $ownersCount = User::where('company_id', $user->company_id)
                ->where('role', 'owner')
                ->count();

            if ($ownersCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => ['You are the only owner in this company and cannot demote yourself.'],
                ]);
            }
        }

        $user->fill(collect($data)->except('role')->toArray());

        if (array_key_exists('role', $data)) {
            $user->role = $data['role'];
        }

        $user->save();

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(null, 204);
    }
}
