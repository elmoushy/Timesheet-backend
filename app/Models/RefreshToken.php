<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RefreshToken extends Model
{
    use HasFactory;

    protected $table = 'refresh_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'device_id',
        'user_agent',
        'ip_address',
        'expires_at',
        'last_used_at',
        'is_revoked',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_revoked' => 'boolean',
    ];

    /**
     * Get the user that owns this refresh token
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id', 'id');
    }

    /**
     * Create a new refresh token for the user
     */
    public static function createForUser(int $userId, array $metadata = []): self
    {
        // Generate raw token with configured length
        $tokenLength = config('sso.refresh_token.length', 32);
        $rawToken = Str::random($tokenLength);

        // Hash the token for secure storage
        $hashedToken = hash('sha256', $rawToken);

        $token = static::create([
            'user_id' => $userId,
            'token' => $hashedToken,
            'device_id' => $metadata['device_id'] ?? null,
            'user_agent' => $metadata['user_agent'] ?? null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'expires_at' => now()->addSeconds(config('sso.refresh_token.ttl_seconds', 2592000)),
        ]);

        // Store the raw token temporarily for return to client
        $token->raw_token = $rawToken;

        \Log::info('Refresh token created', [
            'user_id' => $userId,
            'token_id' => $token->id,
            'raw_token_length' => strlen($rawToken),
            'expires_at' => $token->expires_at->toDateTimeString()
        ]);

        return $token;
    }

    /**
     * Find a valid refresh token
     * This method handles both properly hashed tokens and legacy tokens stored directly
     */
    public static function findValidToken(string $rawToken): ?self
    {
        // First, try to find token by hashing the raw token (proper way)
        $hashedToken = hash('sha256', $rawToken);

        $token = static::where('token', $hashedToken)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($token) {
            return $token;
        }

        // Fallback: Check if the raw token is stored directly (legacy support)
        // This handles tokens that were incorrectly stored without hashing
        $legacyToken = static::where('token', $rawToken)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->first();

        if ($legacyToken) {
            // Log this occurrence for monitoring
            \Log::warning('Found legacy refresh token stored without hashing', [
                'token_id' => $legacyToken->id,
                'user_id' => $legacyToken->user_id,
                'token_length' => strlen($rawToken)
            ]);

            return $legacyToken;
        }

        return null;
    }

    /**
     * Check if token is valid (not revoked and not expired)
     */
    public function isValid(): bool
    {
        return ! $this->is_revoked && $this->expires_at->isFuture();
    }

    /**
     * Revoke this token
     */
    public function revoke(): bool
    {
        return $this->update(['is_revoked' => true]);
    }

    /**
     * Update last used timestamp
     */
    public function updateLastUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Revoke all refresh tokens for a user
     */
    public static function revokeAllForUser(int $userId): int
    {
        return static::where('user_id', $userId)
            ->where('is_revoked', false)
            ->update(['is_revoked' => true]);
    }

    /**
     * Clean up expired tokens
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Identify tokens that might be stored incorrectly (without hashing)
     * Legacy tokens are typically 64 characters (hex) instead of 32 random chars hashed
     */
    public static function findLegacyTokens()
    {
        // Find tokens that are 64 characters long and might be stored directly
        return static::whereRaw('LENGTH(token) = 64')
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->get();
    }

    /**
     * Check if this token appears to be a legacy token (stored without hashing)
     */
    public function isLegacyToken(): bool
    {
        // Legacy tokens are typically 64 hex characters stored directly
        return strlen($this->token) === 64 && ctype_xdigit($this->token);
    }
}
