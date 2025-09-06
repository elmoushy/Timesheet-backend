<?php

namespace App\Services;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Log;

class JwtService
{
    private string $secret;

    private string $algorithm;

    private string $issuer;

    private int $ttlSeconds;

    public function __construct()
    {
        $this->secret = config('sso.jwt.secret') ?: config('app.key');
        $this->algorithm = config('sso.jwt.algorithm', 'HS256');
        $this->issuer = config('sso.jwt.issuer', 'timesheet-api');
        $this->ttlSeconds = config('sso.jwt.ttl_seconds', 900);
    }

    /**
     * Generate a JWT token for the given user
     */
    public function generateToken(int $userId, array $additionalClaims = []): string
    {
        $now = time();
        $payload = array_merge([
            'iss' => $this->issuer, // Issuer
            'aud' => $this->issuer, // Audience
            'iat' => $now, // Issued at
            'nbf' => $now, // Not before
            'exp' => $now + $this->ttlSeconds, // Expiration
            'sub' => (string) $userId, // Subject (user ID)
            'jti' => uniqid(), // JWT ID
        ], $additionalClaims);

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Validate and decode a JWT token
     *
     * @return array|null Returns decoded payload or null if invalid
     */
    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

            return (array) $decoded;
        } catch (ExpiredException $e) {
            Log::info('JWT token expired', ['token' => substr($token, 0, 20).'...']);

            return null;
        } catch (SignatureInvalidException $e) {
            Log::warning('JWT signature invalid', ['token' => substr($token, 0, 20).'...']);

            return null;
        } catch (\Exception $e) {
            Log::error('JWT validation error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 20).'...',
            ]);

            return null;
        }
    }

    /**
     * Extract user ID from token
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->validateToken($token);

        if (! $payload || ! isset($payload['sub'])) {
            return null;
        }

        return (int) $payload['sub'];
    }

    /**
     * Check if token is expired
     */
    public function isTokenExpired(string $token): bool
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

            return time() >= $decoded->exp;
        } catch (\Exception $e) {
            return true; // Assume expired if we can't decode
        }
    }

    /**
     * Get token expiration time
     *
     * @return int|null Unix timestamp or null if invalid
     */
    public function getTokenExpiration(string $token): ?int
    {
        $payload = $this->validateToken($token);

        return $payload['exp'] ?? null;
    }

    /**
     * Generate token with custom TTL
     */
    public function generateTokenWithTtl(int $userId, int $customTtlSeconds, array $additionalClaims = []): string
    {
        $originalTtl = $this->ttlSeconds;
        $this->ttlSeconds = $customTtlSeconds;

        $token = $this->generateToken($userId, $additionalClaims);

        $this->ttlSeconds = $originalTtl; // Restore original TTL

        return $token;
    }
}
