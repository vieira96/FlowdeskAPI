<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\Api\Auth\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $authentication = $this->authService->login($request->validated());

        if ($authentication === null) {
            return response()->json([
                'message' => 'As credenciais informadas são inválidas.',
            ], 422);
        }

        return $this->authenticationResponse($authentication);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(status: 204);
    }

    /**
     * @param  array{user: User, token: string}  $authentication
     */
    private function authenticationResponse(array $authentication, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => new UserResource($authentication['user']),
                'token' => $authentication['token'],
                'token_type' => 'Bearer',
            ],
        ], $status);
    }
}
