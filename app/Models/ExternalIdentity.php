<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalIdentity extends Model
{
    use HasFactory;

    protected $table = 'external_identities';

    protected $fillable = [
        'user_id',
        'provider',
        'external_id',
        'tenant_id',
        'external_email',
        'external_name',
        'provider_data',
        'last_login_at',
    ];

    protected $casts = [
        'provider_data' => 'array',
        'last_login_at' => 'datetime',
    ];

    /**
     * Get the user that owns this external identity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'user_id', 'id');
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Find by provider and external ID
     */
    public static function findByProvider(string $provider, string $externalId): ?self
    {
        return static::where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();
    }

    /**
     * Find by provider and external ID with tenant validation
     */
    public static function findByProviderWithTenant(string $provider, string $externalId, ?string $tenantId = null): ?self
    {
        $query = static::where('provider', $provider)
            ->where('external_id', $externalId);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->first();
    }
}
