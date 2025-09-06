<?php

namespace App\Services;

use App\Models\RefreshToken;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RefreshTokenService
{
    /**
     * Create a new refresh token for the user
     */
    public function createRefreshToken(int $userId, ?Request $request = null): RefreshToken
    {
        $metadata = [];

        if ($request) {
            $metadata = [
                'user_agent' => $request->header('User-Agent'),
                'ip_address' => $request->ip(),
                'device_id' => $request->input('device_id'),
            ];
        }

        $token = RefreshToken::createForUser($userId, $metadata);

        Log::info('Refresh token created', [
            'user_id' => $userId,
            'expires_at' => $token->expires_at,
        ]);

        return $token;
    }

    /**
     * Rotate refresh token (create new one and revoke old)
     *
     * @return array|null [new_token, access_token] or null if invalid
     */
    public function rotateRefreshToken(string $oldTokenRaw, ?Request $request = null): ?array
    {
        try {
            // Find and validate the old token
            $oldToken = RefreshToken::findValidToken($oldTokenRaw);

            if (! $oldToken) {
                Log::warning('Refresh token rotation failed - token not found or invalid');

                return null;
            }

            if (! $oldToken->isValid()) {
                Log::warning('Refresh token rotation failed - token expired or revoked', [
                    'token_id' => $oldToken->id,
                    'expires_at' => $oldToken->expires_at,
                    'is_revoked' => $oldToken->is_revoked,
                ]);

                return null;
            }

            $user = $oldToken->user;

            if (! $user || ! $user->isActive()) {
                Log::warning('Refresh token rotation failed - user inactive or not found', [
                    'user_id' => $oldToken->user_id,
                ]);

                return null;
            }

            // Create new refresh token
            $newRefreshToken = $this->createRefreshToken($user->id, $request);

            // Create new access token
            $jwtService = new JwtService;
            $accessToken = $jwtService->generateToken($user->id, [
                'email' => $user->work_email,
                'name' => $user->getFullNameAttribute(),
            ]);

            // Revoke the old token
            $oldToken->revoke();
            $oldToken->updateLastUsed();

            Log::info('Refresh token rotated successfully', [
                'user_id' => $user->id,
                'old_token_id' => $oldToken->id,
                'new_token_id' => $newRefreshToken->id,
            ]);

            return [
                'refresh_token' => $newRefreshToken->raw_token,
                'access_token' => $accessToken,
                'expires_in' => config('sso.jwt.ttl_seconds', 900),
                'token_type' => 'Bearer',
            ];

        } catch (Exception $e) {
            Log::error('Refresh token rotation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Revoke a refresh token
     */
    public function revokeRefreshToken(string $tokenRaw): bool
    {
        try {
            $token = RefreshToken::findValidToken($tokenRaw);

            if (! $token) {
                Log::info('Attempted to revoke non-existent or invalid refresh token');

                return false;
            }

            $success = $token->revoke();

            if ($success) {
                Log::info('Refresh token revoked', [
                    'token_id' => $token->id,
                    'user_id' => $token->user_id,
                ]);
            }

            return $success;

        } catch (Exception $e) {
            Log::error('Refresh token revocation error', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Revoke all refresh tokens for a user
     *
     * @return int Number of tokens revoked
     */
    public function revokeAllUserTokens(int $userId): int
    {
        try {
            $count = RefreshToken::revokeAllForUser($userId);

            Log::info('All refresh tokens revoked for user', [
                'user_id' => $userId,
                'count' => $count,
            ]);

            return $count;

        } catch (Exception $e) {
            Log::error('Error revoking all user tokens', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Clean up expired refresh tokens
     *
     * @return int Number of tokens cleaned up
     */
    public function cleanupExpiredTokens(): int
    {
        try {
            $count = RefreshToken::cleanupExpired();

            if ($count > 0) {
                Log::info('Expired refresh tokens cleaned up', ['count' => $count]);
            }

            return $count;

        } catch (Exception $e) {
            Log::error('Error cleaning up expired tokens', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get user's active refresh tokens
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserActiveTokens(int $userId)
    {
        return RefreshToken::where('user_id', $userId)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Validate refresh token without consuming it
     */
    public function validateRefreshToken(string $tokenRaw): bool
    {
        $token = RefreshToken::findValidToken($tokenRaw);

        return $token && $token->isValid();
    }

    /**
     * Find and return a valid refresh token
     */
    public function findValidRefreshToken(string $tokenRaw): ?RefreshToken
    {
        $token = RefreshToken::findValidToken($tokenRaw);

        if (! $token || ! $token->isValid()) {
            return null;
        }

        return $token;
    }
}
