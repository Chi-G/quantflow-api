<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Traits\HasApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

final class AuthController extends Controller
{
    use HasApiResponse;

    #[OA\Post(
        path: "/api/v1/auth/register",
        summary: "Register a new user",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "email", "password", "password_confirmation"],
                properties: [
                    new OA\Property(property: "name", type: "string"),
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "password", type: "string"),
                    new OA\Property(property: "password_confirmation", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: "Successful operation"),
            new OA\Response(response: 422, description: "Validation error")
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'tenant_id' => Str::uuid(),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => \App\Enums\UserRole::Admin->value,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success('User registered successfully.', [
            'user' => $user,
            'token' => $token,
        ], [], 201);
    }

    #[OA\Post(
        path: "/api/v1/auth/login",
        summary: "Login user",
        tags: ["Authentication"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["email", "password"],
                properties: [
                    new OA\Property(property: "email", type: "string"),
                    new OA\Property(property: "password", type: "string")
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: "Successful login"),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            return $this->error('Invalid credentials.', [], 401);
        }

        if (!$user->is_active) {
            return $this->error('Account is disabled.', [], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success('Login successful.', [
            'user' => $user,
            'token' => $token,
        ]);
    }

    #[OA\Post(
        path: "/api/v1/auth/logout",
        summary: "Logout user",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Successful logout")
        ]
    )]
    public function logout(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        
        $token = $user->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return $this->success('Logged out successfully.');
    }

    #[OA\Get(
        path: "/api/v1/auth/me",
        summary: "Get authenticated user",
        security: [["bearerAuth" => []]],
        tags: ["Authentication"],
        responses: [
            new OA\Response(response: 200, description: "Successful operation")
        ]
    )]
    public function me(): JsonResponse
    {
        return $this->success('User profile retrieved.', [
            'user' => auth()->user()
        ]);
    }
}
