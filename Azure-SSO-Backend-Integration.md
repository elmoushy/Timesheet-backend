# Azure SSO Backend Integration Guide

## Overview

This document provides a framework-agnostic guide for implementing Azure Single Sign-On (SSO) integration on the backend using Microsoft Entra ID (formerly Azure Active Directory). The implementation follows the OAuth 2.0/OpenID Connect protocols to enable secure authentication and user provisioning.

## Architecture Overview

```
Frontend Application
        ↓
    [Access Token]
        ↓
Backend SSO Exchange Endpoint
        ↓
Azure Entra ID Validation
        ↓
User Provisioning/Lookup
        ↓
Generate App-Specific Tokens
        ↓
Return User Data + Tokens
```

## Core Components

### 1. SSO Exchange Endpoint

**Purpose**: Validates Azure-issued access tokens and exchanges them for application-specific tokens.

**Flow**:
1. Receive Azure access token from frontend
2. Validate token with Microsoft Entra ID
3. Extract user information from validated token
4. Provision or lookup user in local database
5. Generate application-specific JWT and refresh tokens
6. Return user data and tokens

**Key Implementation Points**:
- Always validate tokens against Microsoft's JWKS (JSON Web Key Set)
- Handle token expiration gracefully
- Implement proper error handling for invalid/expired tokens
- Use secure token exchange patterns

### 2. Token Validation Service

**Purpose**: Validates Microsoft Entra ID access tokens and extracts user claims.

**Validation Steps**:
1. **Signature Verification**: Validate JWT signature using Microsoft's public keys
2. **Audience Verification**: Ensure token is intended for your application
3. **Issuer Verification**: Confirm token was issued by Microsoft Entra ID
4. **Expiration Check**: Verify token hasn't expired
5. **Scope Validation**: Check required scopes are present

**Required Claims**:
- `sub`: Subject (user identifier)
- `email` or `preferred_username`: User email
- `given_name`: First name
- `family_name`: Last name
- `aud`: Audience
- `iss`: Issuer
- `exp`: Expiration timestamp

### 3. User Provisioning Service

**Purpose**: Creates or updates user records based on Azure user information.

**Provisioning Logic**:
```
IF user exists in local database:
    UPDATE user with latest Azure information
ELSE:
    CREATE new user with Azure information
    ASSIGN default roles/permissions
```

**Key Considerations**:
- Use Azure's unique identifier (`sub` claim) for user matching
- Handle email changes gracefully
- Implement proper default role assignment
- Consider user deactivation scenarios

## Configuration Requirements

### Azure App Registration

1. **Application Registration**:
   - Register application in Azure Portal
   - Configure redirect URIs for your frontend
   - Generate client secret (store securely)
   - Note application (client) ID and tenant ID

2. **API Permissions**:
   - `User.Read`: Basic user profile access
   - Additional Graph API permissions as needed

3. **Token Configuration**:
   - Enable ID tokens and access tokens
   - Configure token lifetime policies

### Backend Configuration

**Required Environment Variables**:
```bash
# Azure Configuration
AZURE_CLIENT_ID=your-client-id
AZURE_TENANT_ID=your-tenant-id
AZURE_CLIENT_SECRET=your-client-secret

# Token Validation
SSO_EXPECTED_AUDIENCE=00000003-0000-0000-c000-000000000000  # Microsoft Graph
SSO_REQUIRED_SCOPE=User.Read

# Application JWT Settings
APP_JWT_ISSUER=your-app-name
APP_JWT_TTL_SECONDS=900
APP_REFRESH_TTL_SECONDS=2592000
```

**OIDC Endpoints**:
```bash
# Replace {tenant-id} with your actual tenant ID
AUTHORITY=https://login.microsoftonline.com/{tenant-id}/v2.0
JWKS_URI=https://login.microsoftonline.com/{tenant-id}/discovery/v2.0/keys
TOKEN_ENDPOINT=https://login.microsoftonline.com/{tenant-id}/oauth2/v2.0/token
```

## Implementation Steps

### Step 1: Set Up Token Validation

```pseudo
function validateAzureToken(accessToken) {
    // 1. Decode JWT header to get key ID
    header = decodeJWTHeader(accessToken)
    
    // 2. Fetch Microsoft's public keys
    jwksKeys = fetchFromCache(JWKS_URI) || fetchJWKS(JWKS_URI)
    
    // 3. Find matching public key
    publicKey = findKeyById(jwksKeys, header.kid)
    
    // 4. Verify signature
    if (!verifySignature(accessToken, publicKey)) {
        throw InvalidTokenError("Invalid signature")
    }
    
    // 5. Decode and validate claims
    claims = decodeJWT(accessToken)
    
    // 6. Validate required claims
    validateClaims(claims, {
        aud: SSO_EXPECTED_AUDIENCE,
        iss: EXPECTED_ISSUER,
        exp: currentTimestamp(),
        scope: SSO_REQUIRED_SCOPE
    })
    
    return claims
}
```

### Step 2: Implement User Provisioning

```pseudo
function provisionUser(azureClaims) {
    // 1. Look for existing user by Azure subject ID
    user = findUserByAzureId(azureClaims.sub)
    
    if (!user) {
        // 2. Create new user
        user = createUser({
            azureId: azureClaims.sub,
            email: azureClaims.email || azureClaims.preferred_username,
            firstName: azureClaims.given_name,
            lastName: azureClaims.family_name,
            isActive: true,
            createdAt: now()
        })
        
        // 3. Assign default roles
        assignDefaultRoles(user)
        
        logInfo("New user provisioned", { userId: user.id, email: user.email })
        
        return { user: user, created: true }
    } else {
        // 4. Update existing user
        updateUser(user, {
            email: azureClaims.email || azureClaims.preferred_username,
            firstName: azureClaims.given_name,
            lastName: azureClaims.family_name,
            lastLogin: now()
        })
        
        return { user: user, created: false }
    }
}
```

### Step 3: Create SSO Exchange Endpoint

```pseudo
POST /api/sso/exchange
Content-Type: application/json
{
    "access_token": "azure-access-token-here"
}

function handleSSOExchange(request) {
    try {
        // 1. Extract Azure access token
        azureToken = request.body.access_token
        
        // 2. Validate Azure token
        claims = validateAzureToken(azureToken)
        
        // 3. Provision/lookup user
        provisionResult = provisionUser(claims)
        user = provisionResult.user
        
        // 4. Check if user is active
        if (!user.isActive) {
            return error(403, "Account disabled")
        }
        
        // 5. Generate application tokens
        appJWT = generateJWT(user.id, APP_JWT_TTL_SECONDS)
        refreshToken = createRefreshToken(user.id, request.clientInfo)
        
        // 6. Return successful response
        return success({
            access_token: appJWT,
            refresh_token: refreshToken.token,
            token_type: "Bearer",
            expires_in: APP_JWT_TTL_SECONDS,
            user: {
                id: user.id,
                email: user.email,
                firstName: user.firstName,
                lastName: user.lastName,
                isActive: user.isActive,
                roles: user.roles
            }
        })
        
    } catch (error) {
        logError("SSO exchange failed", error)
        return error(500, "Exchange failed")
    }
}
```

## Security Considerations

### Token Security

1. **Never Log Sensitive Tokens**: Exclude access tokens from logs
2. **Secure Token Storage**: Store refresh tokens securely (hashed/encrypted)
3. **Token Rotation**: Implement refresh token rotation for enhanced security
4. **Short-Lived Access Tokens**: Keep application JWT lifetime short (15 minutes)

### Validation Best Practices

1. **Always Validate Signatures**: Never skip JWT signature verification
2. **Verify All Claims**: Check audience, issuer, expiration, and scopes
3. **Cache JWKS Keys**: Cache Microsoft's public keys but refresh periodically
4. **Handle Clock Skew**: Allow small time differences in expiration checks

### Error Handling

1. **Generic Error Messages**: Don't expose internal error details
2. **Rate Limiting**: Implement rate limiting on SSO endpoints
3. **Audit Logging**: Log all authentication attempts (success/failure)
4. **Graceful Degradation**: Handle Azure service outages appropriately

## Database Schema Considerations

### User Table Structure
```sql
CREATE TABLE employees (
    id INTEGER PRIMARY KEY,
    azure_id VARCHAR(255) UNIQUE NOT NULL,  -- Azure 'sub' claim
    employee_code VARCHAR(50),
    work_email VARCHAR(255),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    last_login TIMESTAMP
);

CREATE INDEX idx_employees_azure_id ON employees(azure_id);
CREATE INDEX idx_employees_email ON employees(work_email);
```

### Refresh Token Table
```sql
CREATE TABLE refresh_tokens (
    id INTEGER PRIMARY KEY,
    user_id INTEGER NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    client_ip VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES employees(id)
);

CREATE INDEX idx_refresh_tokens_user_id ON refresh_tokens(user_id);
CREATE INDEX idx_refresh_tokens_expires_at ON refresh_tokens(expires_at);
```

## Development vs Production

### Development Settings
- Skip token expiration for testing: `SSO_SKIP_EXPIRATION_CHECK=true`
- Use development Azure app registration
- Enable verbose logging
- Allow localhost redirects

### Production Settings
- Strict token validation (no skips)
- Production Azure app registration
- Minimal logging of sensitive data
- HTTPS-only redirects
- Implement proper monitoring and alerting

## Troubleshooting Common Issues

### Token Validation Failures
- **Signature Invalid**: Check if JWKS keys are current
- **Audience Mismatch**: Verify expected audience configuration
- **Expired Token**: Check clock synchronization
- **Missing Scopes**: Ensure Azure app has correct permissions

### User Provisioning Issues
- **Duplicate Users**: Use Azure `sub` claim as unique identifier
- **Missing Claims**: Check Azure token configuration
- **Role Assignment**: Verify default role assignment logic

### Performance Considerations
- **JWKS Caching**: Cache Microsoft's public keys (refresh every 24 hours)
- **Database Optimization**: Index on azure_id and email fields
- **Connection Pooling**: Use database connection pooling
- **Rate Limiting**: Implement to prevent abuse

## Testing Strategy

1. **Unit Tests**: Test token validation logic independently
2. **Integration Tests**: Test full SSO flow with mock Azure responses
3. **Manual Testing**: Use Azure's token introspection endpoint
4. **Security Testing**: Test with invalid/expired tokens
5. **Load Testing**: Verify performance under load

## Monitoring and Maintenance

### Key Metrics to Monitor
- SSO exchange success/failure rates
- Token validation response times
- User provisioning frequency
- Authentication error patterns

### Maintenance Tasks
- Rotate client secrets periodically
- Update JWKS cache refresh intervals
- Clean up expired refresh tokens
- Monitor Azure service health

This implementation provides a secure, scalable foundation for Azure SSO integration that can be adapted to any backend framework while following security best practices and OAuth 2.0 standards.
