<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\ExternalIdentity;
use App\Models\Role;
use App\Models\UserRole;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserProvisioningService
{
    private string $defaultRoleName;

    private string $providerName;

    public function __construct()
    {
        $this->defaultRoleName = config('sso.user_provisioning.default_role', 'Employee');
        $this->providerName = config('sso.user_provisioning.provider_name', 'entra');
    }

    /**
     * Find or create user based on external identity
     *
     * @param  array  $userInfo  User information from SSO provider
     * @return array Result with user and creation status
     *
     * @throws Exception
     */
    public function findOrCreateUser(array $userInfo): array
    {
        try {
            DB::beginTransaction();

            // First, try to find existing external identity
            $externalIdentity = ExternalIdentity::findByProviderWithTenant(
                $this->providerName,
                $userInfo['external_id'],
                $userInfo['tenant_id']
            );

            if ($externalIdentity) {
                // User exists, update last login and return
                $user = $externalIdentity->user;

                if (! $user) {
                    throw new Exception('External identity exists but linked user not found');
                }

                // Check if user is active
                if (! $user->isActive()) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'error' => 'user_disabled',
                        'message' => 'User account is disabled',
                        'user' => null,
                    ];
                }

                // Update external identity
                $externalIdentity->update([
                    'external_email' => $userInfo['email'],
                    'external_name' => $userInfo['name'],
                    'provider_data' => $userInfo['raw_claims'],
                    'last_login_at' => now(),
                ]);

                DB::commit();

                Log::info('Existing user logged in via SSO', [
                    'user_id' => $user->id,
                    'email' => $userInfo['email'],
                    'external_id' => $userInfo['external_id'],
                ]);

                return [
                    'success' => true,
                    'user' => $user,
                    'created' => false,
                    'external_identity' => $externalIdentity,
                ];
            }

            // Try to find user by email if no external identity exists
            $existingUser = null;
            if ($userInfo['email']) {
                $existingUser = Employee::where('work_email', $userInfo['email'])->first();
            }

            if ($existingUser) {
                // Link existing user to external identity
                $externalIdentity = $this->createExternalIdentity($existingUser->id, $userInfo);

                DB::commit();

                Log::info('Existing user linked to external identity', [
                    'user_id' => $existingUser->id,
                    'email' => $userInfo['email'],
                    'external_id' => $userInfo['external_id'],
                ]);

                return [
                    'success' => true,
                    'user' => $existingUser,
                    'created' => false,
                    'external_identity' => $externalIdentity,
                ];
            }

            // Create new user
            $newUser = $this->createNewUser($userInfo);
            $externalIdentity = $this->createExternalIdentity($newUser->id, $userInfo);
            $this->assignDefaultRole($newUser);

            DB::commit();

            Log::info('New user created via SSO', [
                'user_id' => $newUser->id,
                'email' => $userInfo['email'],
                'external_id' => $userInfo['external_id'],
            ]);

            return [
                'success' => true,
                'user' => $newUser,
                'created' => true,
                'external_identity' => $externalIdentity,
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('User provisioning failed', [
                'error' => $e->getMessage(),
                'user_info' => $userInfo,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a new user from external identity information
     *
     * @throws Exception
     */
    private function createNewUser(array $userInfo): Employee
    {
        // Parse name components
        $firstName = $userInfo['given_name'] ?? '';
        $lastName = $userInfo['family_name'] ?? '';

        // If no given/family names, try to parse from full name
        if (empty($firstName) && ! empty($userInfo['name'])) {
            $nameParts = explode(' ', trim($userInfo['name']), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
        }

        // Generate employee code
        $employeeCode = $this->generateEmployeeCode($firstName, $lastName);

        // Create user with all required fields for SQLite constraints
        $userData = [
            'employee_code' => $employeeCode,
            'first_name' => $firstName ?: 'Unknown',
            'last_name' => $lastName ?: 'User',
            'work_email' => $userInfo['email'],
            'user_status' => 'active',
            'employee_type' => 'external_sso',
            'birth_date' => '1900-01-01', // Default birth date for SSO users
            'gender' => 'Not Specified', // Required field
            'marital_status' => 'Not Specified', // Required field
            'nationality' => 'Not Specified', // Required field
            'id_type' => 'SSO', // Required field - indicate this is SSO user
            'id_number' => 'SSO-'.substr($userInfo['external_id'], 0, 20), // Required field - use part of external_id
            'id_expiry_date' => '2099-12-31', // Required field - far future date for SSO users
            'job_title' => 'External User', // Required field
            'contract_start_date' => now()->format('Y-m-d'), // Required field - today's date
            'contract_end_date' => '2099-12-31', // Required field - far future date
        ];

        return Employee::create($userData);
    }

    /**
     * Create external identity record
     */
    private function createExternalIdentity(int $userId, array $userInfo): ExternalIdentity
    {
        return ExternalIdentity::create([
            'user_id' => $userId,
            'provider' => $this->providerName,
            'external_id' => $userInfo['external_id'],
            'tenant_id' => $userInfo['tenant_id'],
            'external_email' => $userInfo['email'],
            'external_name' => $userInfo['name'],
            'provider_data' => $userInfo['raw_claims'],
            'last_login_at' => now(),
        ]);
    }

    /**
     * Assign default role to user
     *
     * @throws Exception
     */
    private function assignDefaultRole(Employee $user): void
    {
        $role = Role::where('name', $this->defaultRoleName)
            ->where('is_active', true)
            ->first();

        if (! $role) {
            throw new Exception("Default role '{$this->defaultRoleName}' not found or inactive");
        }

        // Check if user already has this role
        $existingRole = UserRole::where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->first();

        if (! $existingRole) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
                'is_active' => true,
                'assigned_by' => null, // System assigned
            ]);

            Log::info('Default role assigned to new user', [
                'user_id' => $user->id,
                'role' => $this->defaultRoleName,
            ]);
        }
    }

    /**
     * Generate unique employee code
     */
    private function generateEmployeeCode(string $firstName, string $lastName): string
    {
        // Create base code from names
        $baseCode = strtoupper(substr($firstName, 0, 1).substr($lastName, 0, 3));
        $baseCode = preg_replace('/[^A-Z0-9]/', '', $baseCode);

        if (strlen($baseCode) < 2) {
            $baseCode = 'SSO'.$baseCode;
        }

        // Ensure uniqueness
        $counter = 1;
        $code = $baseCode.str_pad($counter, 3, '0', STR_PAD_LEFT);

        while (Employee::where('employee_code', $code)->exists()) {
            $counter++;
            $code = $baseCode.str_pad($counter, 3, '0', STR_PAD_LEFT);

            // Prevent infinite loop
            if ($counter > 999) {
                $code = 'SSO'.Str::random(4).time();
                break;
            }
        }

        return $code;
    }

    /**
     * Get user roles for response
     */
    public function getUserRoles(Employee $user): array
    {
        return $user->activeUserRoles()
            ->with('role')
            ->get()
            ->pluck('role.name')
            ->toArray();
    }

    /**
     * Validate tenant if user has existing external identity
     */
    public function validateTenant(string $externalId, string $tenantId): bool
    {
        $identity = ExternalIdentity::where('provider', $this->providerName)
            ->where('external_id', $externalId)
            ->first();

        if ($identity && $identity->tenant_id !== $tenantId) {
            Log::warning('Tenant mismatch for external identity', [
                'external_id' => $externalId,
                'existing_tenant' => $identity->tenant_id,
                'provided_tenant' => $tenantId,
            ]);

            return false;
        }

        return true;
    }
}
