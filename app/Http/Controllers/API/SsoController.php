<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\SsoExchangeRequest;
use App\Models\PageRolePermission;
use App\Models\RefreshToken;
use App\Services\EntraSsoService;
use App\Services\JwtService;
use App\Services\RefreshTokenService;
use App\Services\UserProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SsoController extends Controller
{
    private EntraSsoService $entraSsoService;

    private UserProvisioningService $provisioningService;

    private JwtService $jwtService;

    private RefreshTokenService $refreshTokenService;

    public function __construct(
        EntraSsoService $entraSsoService,
        UserProvisioningService $provisioningService,
        JwtService $jwtService,
        RefreshTokenService $refreshTokenService
    ) {
        $this->entraSsoService = $entraSsoService;
        $this->provisioningService = $provisioningService;
        $this->jwtService = $jwtService;
        $this->refreshTokenService = $refreshTokenService;
    }

    /**
     * Exchange Microsoft access token for application JWT
     */
    public function exchange(SsoExchangeRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Accept either access_token or id_token parameter
            // For SSO authentication, ID token is preferred as it contains user identity
            $token = $request->id_token ?? $request->access_token;

            // Validate Microsoft token and extract user info
            $userInfo = $this->entraSsoService->validateAccessToken($token);

            if (! $userInfo) {
                return response()->json([
                    'error' => 'invalid_token',
                    'message' => 'Invalid or expired Microsoft token',
                ], Response::HTTP_UNAUTHORIZED);
            }

            Log::info('SSO exchange initiated', [
                'user_email' => $userInfo['email'] ?? 'unknown',
                'user_id' => $userInfo['external_id'] ?? 'unknown',
            ]);

            // Find or create user with auto-provisioning
            $provisioningResult = $this->provisioningService->findOrCreateUser($userInfo);

            if (! $provisioningResult['success']) {
                return response()->json([
                    'error' => $provisioningResult['error'] ?? 'provisioning_failed',
                    'message' => $provisioningResult['message'] ?? 'Failed to provision user account',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $user = $provisioningResult['user'];

            // Check if user is active
            if (! $user->isActive()) {
                Log::warning('SSO attempt by disabled user', [
                    'user_id' => $user->id,
                    'email' => $user->work_email,
                ]);

                return response()->json([
                    'error' => 'account_disabled',
                    'message' => 'Your account has been disabled. Please contact system administrator.',
                ], Response::HTTP_FORBIDDEN);
            }

            // Generate application JWT token
            $appToken = $this->jwtService->generateToken($user->id);

            // Generate refresh token
            $refreshToken = $this->refreshTokenService->createRefreshToken(
                $user->id,
                $request
            );

            // Load active user roles with their roles
            $user->load(['activeUserRoles.role']);

            // Get allowed pages for all user's active roles
            $allowedPages = collect();

            if ($user->activeUserRoles->isNotEmpty()) {
                // Get all active role IDs from user roles
                $roleIds = $user->activeUserRoles->pluck('role_id')->toArray();

                // Get all allowed pages for these roles (avoiding duplicates)
                $allowedPages = PageRolePermission::with('page')
                    ->whereIn('role_id', $roleIds)
                    ->where('is_active', true)
                    ->whereHas('page', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->get()
                    ->pluck('page')
                    ->unique('id') // Remove duplicate pages by ID
                    ->map(function ($page) {
                        return [
                            'id' => $page->id,
                            'name' => $page->name,
                        ];
                    })
                    ->values(); // Reset array keys after unique
            }

            DB::commit();

            Log::info('SSO exchange successful', [
                'user_id' => $user->id,
                'email' => $user->work_email,
                'was_provisioned' => $provisioningResult['created'] ?? false,
            ]);

            return response()->json([
                'token' => $appToken,
                'refresh_token' => $refreshToken->token,
                'token_type' => 'Bearer',
                'expires_in' => config('sso.jwt.ttl_seconds'),
                'allowed_pages' => $allowedPages,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->work_email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->first_name.' '.$user->last_name,
                    'employee_code' => $user->employee_code,
                    'is_active' => $user->isActive(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('SSO exchange failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['access_token']), // Don't log sensitive token
            ]);

            return response()->json([
                'error' => 'exchange_failed',
                'message' => 'Token exchange failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Refresh access token using refresh token
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $refreshTokenValue = $request->refresh_token;

            // Find and validate refresh token
            $refreshToken = $this->refreshTokenService->findValidRefreshToken($refreshTokenValue);

            if (! $refreshToken) {
                return response()->json([
                    'error' => 'invalid_refresh_token',
                    'message' => 'Invalid or expired refresh token',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $user = $refreshToken->user;

            // Check if user is still active
            if (! $user->isActive()) {
                // Revoke the refresh token for disabled user
                $this->refreshTokenService->revokeRefreshToken($refreshToken);

                return response()->json([
                    'error' => 'account_disabled',
                    'message' => 'Your account has been disabled. Please contact system administrator.',
                ], Response::HTTP_FORBIDDEN);
            }

            // Generate new access token
            $newAccessToken = $this->jwtService->generateToken($user->id);

            // Rotate refresh token (revoke old, create new)
            $tokenRotationResult = $this->refreshTokenService->rotateRefreshToken(
                $refreshTokenValue,
                $request
            );

            if (! $tokenRotationResult) {
                DB::rollBack();

                return response()->json([
                    'error' => 'token_rotation_failed',
                    'message' => 'Failed to rotate refresh token',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            DB::commit();

            Log::info('Token refresh successful', [
                'user_id' => $user->emp_id,
                'email' => $user->work_email,
            ]);

            return response()->json([
                'access_token' => $tokenRotationResult['access_token'],
                'refresh_token' => $tokenRotationResult['refresh_token'],
                'token_type' => $tokenRotationResult['token_type'],
                'expires_in' => $tokenRotationResult['expires_in'],
                'user' => [
                    'id' => $user->emp_id,
                    'email' => $user->work_email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'employee_code' => $user->emp_code,
                    'is_active' => $user->isActive(),
                    'roles' => $user->roles->pluck('role_name')->toArray(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Token refresh failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'refresh_failed',
                'message' => 'Token refresh failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Logout user and revoke refresh token
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            // Get refresh token from request
            $refreshTokenValue = $request->input('refresh_token');

            if ($refreshTokenValue) {
                // Find and revoke refresh token
                $refreshToken = RefreshToken::where('token', $refreshTokenValue)
                    ->where('expires_at', '>', now())
                    ->first();

                if ($refreshToken) {
                    $this->refreshTokenService->revokeRefreshToken($refreshToken);

                    Log::info('User logged out', [
                        'user_id' => $refreshToken->user_id,
                        'refresh_token_id' => $refreshToken->id,
                    ]);
                }
            }

            // Get authenticated user if available (from JWT middleware)
            $user = $request->attributes->get('auth_user');
            if ($user) {
                Log::info('User logout via JWT', [
                    'user_id' => $user->emp_id,
                    'email' => $user->email,
                ]);
            }

            return response()->json([
                'message' => 'Successfully logged out',
            ]);

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still return success for logout - we don't want to prevent users from logging out
            return response()->json([
                'message' => 'Logout completed',
            ]);
        }
    }

    /**
     * Get current user info (requires JWT authentication)
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->attributes->get('auth_user');

            if (! $user) {
                return response()->json([
                    'error' => 'user_not_found',
                    'message' => 'Authenticated user not found',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return response()->json([
                'user' => [
                    'id' => $user->emp_id,
                    'email' => $user->email,
                    'first_name' => $user->fname,
                    'last_name' => $user->lname,
                    'employee_code' => $user->emp_code,
                    'department' => $user->department?->dept_name,
                    'is_active' => $user->isActive(),
                    'roles' => $user->roles->pluck('role_name')->toArray(),
                    'last_login' => $user->last_login_at?->toISOString(),
                    'created_at' => $user->created_at?->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get user info failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'user_info_failed',
                'message' => 'Failed to retrieve user information',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
