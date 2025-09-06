<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class JwtAuthMiddleware
{
    private JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Get token from Authorization header
            $authHeader = $request->header('Authorization');

            if (! $authHeader) {
                return $this->unauthorizedResponse('Authorization header missing');
            }

            // Extract Bearer token
            if (! str_starts_with($authHeader, 'Bearer ')) {
                return $this->unauthorizedResponse('Invalid authorization format. Expected: Bearer <token>');
            }

            $token = substr($authHeader, 7); // Remove "Bearer " prefix

            if (empty($token)) {
                return $this->unauthorizedResponse('Token is empty');
            }

            // Validate the JWT token
            $payload = $this->jwtService->validateToken($token);

            if (! $payload) {
                return $this->unauthorizedResponse('Invalid or expired token');
            }

            // Extract user ID from token
            $userId = isset($payload['sub']) ? (int) $payload['sub'] : null;

            if (! $userId) {
                return $this->unauthorizedResponse('Invalid token payload');
            }

            // Find the user
            $user = Employee::find($userId);

            if (! $user) {
                Log::warning('JWT token references non-existent user', ['user_id' => $userId]);

                return $this->unauthorizedResponse('User not found');
            }

            // Check if user is active
            if (! $user->isActive()) {
                Log::info('JWT token used by inactive user', ['user_id' => $userId]);

                return $this->unauthorizedResponse('User account is disabled');
            }

            // Set authenticated user in request and Auth facade
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            // Also manually set the user for Auth facade
            \Illuminate\Support\Facades\Auth::setUser($user);

            // Add JWT payload to request for potential use
            $request->attributes->set('jwt_payload', $payload);
            $request->attributes->set('auth_user', $user);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('JWT middleware error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_path' => $request->path(),
            ]);

            return $this->unauthorizedResponse('Authentication failed');
        }
    }

    /**
     * Return unauthorized response
     */
    private function unauthorizedResponse(string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => 'unauthorized',
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
