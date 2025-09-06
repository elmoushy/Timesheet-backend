<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Single Sign-On Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures Microsoft Entra ID SSO integration for the
    | Timesheet application backend API.
    |
    */

    'provider' => env('SSO_PROVIDER', 'entra'),

    'azure' => [
        'client_id' => env('AZURE_CLIENT_ID'),
        'tenant_id' => env('AZURE_TENANT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'secret_id' => env('AZURE_SECRET_ID'),

        // OIDC Discovery URLs
        'oidc_discovery_url' => 'https://login.microsoftonline.com/'.env('AZURE_TENANT_ID').'/v2.0/.well-known/openid_configuration',
        'jwks_uri' => 'https://login.microsoftonline.com/'.env('AZURE_TENANT_ID').'/discovery/v2.0/keys',

        // Token validation settings
        'expected_audience' => env('SSO_EXPECTED_AUDIENCE'),
        'required_scope' => env('SSO_REQUIRED_SCOPE', 'access_as_user'),

        // Cache settings for JWKS and OIDC metadata
        'jwks_cache_ttl' => 3600, // 1 hour
        'oidc_cache_ttl' => 3600, // 1 hour

        // Development settings
        'skip_expiration_check' => env('SSO_SKIP_EXPIRATION_CHECK', false),
    ],

    'jwt' => [
        'issuer' => env('APP_JWT_ISSUER', 'timesheet-api'),
        'ttl_seconds' => (int) env('APP_JWT_TTL_SECONDS', 7200), // 2 hours
        'algorithm' => 'HS256',
        'secret' => env('APP_KEY'), // Use Laravel app key for JWT signing
    ],

    'refresh_token' => [
        'ttl_seconds' => (int) env('APP_REFRESH_TTL_SECONDS', 2592000), // 30 days
        'length' => 32, // Random token length
    ],

    'user_provisioning' => [
        'default_role' => 'Employee',
        'provider_name' => 'entra',
        'auto_create' => true,
        'allowed_domains' => [], // Empty = allow all domains
    ],
];
