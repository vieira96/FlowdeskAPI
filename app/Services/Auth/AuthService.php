<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}|null
     */
    public function login(array $credentials): ?array
    {
        $user = User::query()->with('role')->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return null;
        }

        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $user->tokens()->delete();
    }
}
