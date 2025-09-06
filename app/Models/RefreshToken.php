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
        $rawToken = Str::random(config('sso.refresh_token.length', 32));
        $hashedToken = hash('sha256', $rawToken);

        $token = static::create([
            'user_id' => $userId,
            'token' => $hashedToken,
            'device_id' => $metadata['device_id'] ?? null,
            'user_agent' => $metadata['user_agent'] ?? null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'expires_at' => now()->addSeconds(config('sso.refresh_token.ttl_seconds', 2592000)),
        ]);

        // Store the raw token temporarily for return
        $token->raw_token = $rawToken;

        return $token;
    }

    /**
     * Find a valid refresh token
     */
    public static function findValidToken(string $rawToken): ?self
    {
        $hashedToken = hash('sha256', $rawToken);

        return static::where('token', $hashedToken)
            ->where('is_revoked', false)
            ->where('expires_at', '>', now())
            ->first();
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
}
