# Electron Desktop Application Integration

## Overview
This document outlines how the Laravel POS backend is configured for seamless integration with Electron desktop applications.

---

## 1. CORS Configuration

### Configured Origins
The backend accepts requests from:
- **Development**: `localhost:8080`, `127.0.0.1:8080` (Electron dev server)
- **File Protocol**: `file://*` (Local Electron app files)
- **App Protocol**: `app://electron` (Production Electron bundle)
- **Pattern Matching**: 
  - Any port on localhost (regex)
  - Any HTTPS domain (regex)
  - Custom app:// protocol origins

### CORS Headers
- **Allowed Methods**: GET, POST, PUT, DELETE, PATCH, OPTIONS
- **Allowed Headers**: Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-TOKEN
- **Exposed Headers**: X-RateLimit-Limit, X-RateLimit-Remaining, X-Version
- **Credentials**: Enabled (`supports_credentials: true`)

### Configuration File
See [config/cors.php](config/cors.php) for detailed CORS settings.

---

## 2. Authentication & Tokens

### Sanctum Configuration
Electron desktop applications use Sanctum Personal Access Tokens for authentication.

**Key Features:**
- **Token Expiration**: 30 days (43,200 minutes)
- **Stateful Domains**: localhost, 127.0.0.1, app://electron
- **Use Case**: Long-running desktop sessions that may stay open for weeks

### Token Generation Flow
```
Electron App → POST /api/v1/auth/login (email + password)
              ← Personal Access Token (valid for 30 days)
              
Electron App → Stores token securely in app state/database
              Uses token in Authorization header for all requests
```

### Token Usage
```
Authorization: Bearer <token>
```

### Configuration File
See [config/sanctum.php](config/sanctum.php) for detailed Sanctum settings.

---

## 3. Health Check Endpoints

All health endpoints are **public** (no authentication required).

### 3.1 Basic Health Check
```
GET /api/v1/health
```

**Response** (200 OK):
```json
{
  "status": "healthy",
  "timestamp": "2024-01-28T12:30:45Z",
  "api": {
    "version": "1.0.0",
    "name": "POS API"
  },
  "services": {
    "database": {
      "status": "connected",
      "ping": true
    },
    "cache": {
      "status": "connected",
      "ping": true
    }
  }
}
```

**Use Case**: Electron app performs periodic health checks to ensure backend is available.

---

### 3.2 Detailed Health Check
```
GET /api/v1/health/detailed
```

**Response** (200 OK):
```json
{
  "status": "healthy",
  "api": {
    "version": "1.0.0",
    "name": "POS API",
    "environment": "production"
  },
  "framework": {
    "laravel": "11.0.0",
    "php": "8.2.0"
  },
  "database": {
    "status": "connected",
    "version": "8.0.0"
  },
  "services": {
    "cache": {
      "status": "connected",
      "driver": "redis"
    },
    "queue": {
      "status": "connected",
      "driver": "redis"
    }
  },
  "memory": {
    "usage_mb": 45.2,
    "limit_mb": 128.0,
    "usage_percentage": 35.3
  }
}
```

**Use Case**: Diagnostic information for debugging and monitoring.

---

### 3.3 Kubernetes Liveness Probe
```
GET /api/v1/health/live
```

**Response** (200 OK):
```json
{
  "alive": true
}
```

**Use Case**: Container orchestration systems check if API is still running.

---

### 3.4 Kubernetes Readiness Probe
```
GET /api/v1/health/ready
```

**Response** (200 OK):
```json
{
  "ready": true
}
```

**Response** (503 Service Unavailable):
```json
{
  "ready": false
}
```

**Use Case**: Check if API is ready to accept requests (database connected, etc.).

---

## 4. Version Endpoints

All version endpoints are **public** (no authentication required).

### 4.1 Current Version
```
GET /api/v1/version
```

**Response** (200 OK):
```json
{
  "version": "1.0.0",
  "name": "POS System",
  "environment": "production",
  "timestamp": "2024-01-28T12:30:45Z"
}
```

**Headers**:
```
X-Version: 1.0.0
```

**Use Case**: Quick version check for update notifications.

---

### 4.2 Detailed Version Information
```
GET /api/v1/version/detailed
```

**Response** (200 OK):
```json
{
  "api": {
    "version": "1.0.0",
    "release": "stable",
    "build": "20240128.001"
  },
  "framework": {
    "laravel": "11.0.0",
    "php": "8.2.0"
  },
  "database": {
    "version": "8.0.0"
  },
  "features": [
    "api_v1",
    "websockets",
    "authentication",
    "rate_limiting",
    "audit_logging",
    "encryption",
    "queue_jobs"
  ],
  "supported_clients": {
    "electron": ">=1.0.0",
    "web": ">=1.0.0",
    "mobile": ">=1.0.0"
  },
  "endpoints": {
    "api": "/api/v1",
    "health": "/api/v1/health",
    "version": "/api/v1/version"
  }
}
```

**Use Case**: Detailed information about server capabilities and supported client versions.

---

### 4.3 Check Client Compatibility
```
GET /api/v1/version/compatibility?client=electron&version=1.0.0
```

**Query Parameters**:
- `client` (required): Client type - `electron`, `web`, or `mobile`
- `version` (required): Client version (semver format)

**Response** (200 OK - Compatible):
```json
{
  "compatible": true,
  "client": "electron",
  "client_version": "1.0.0",
  "required_version": "1.0.0",
  "message": "Client version is compatible"
}
```

**Response** (426 Upgrade Required - Incompatible):
```json
{
  "compatible": false,
  "client": "electron",
  "client_version": "0.9.0",
  "required_version": "1.0.0",
  "message": "Client version is outdated. Please upgrade to v1.0.0 or later",
  "upgrade_url": "https://github.com/yourusername/pos-electron/releases"
}
```

**Response** (400 Bad Request - Unknown Client):
```json
{
  "success": false,
  "message": "Unknown client type: invalid_client"
}
```

**Use Case**: Electron app checks if current version is compatible with server; prompts upgrade if needed.

---

### 4.4 Changelog / Release Notes
```
GET /api/v1/version/changelog
GET /api/v1/version/changelog?limit=5
GET /api/v1/version/changelog?version=1.0.0
```

**Query Parameters**:
- `limit` (optional, default: 10): Number of releases to return
- `version` (optional): Get specific release information

**Response** (200 OK):
```json
{
  "releases": [
    {
      "version": "1.2.0",
      "release_date": "2024-02-15",
      "changes": "Version 1.2.0 - Minor Features & Bug Fixes",
      "features": [
        "Dark mode support",
        "Batch operations UI"
      ],
      "bugfixes": [
        "Fixed token expiration handling",
        "Improved error messages"
      ],
      "security": [
        "Updated dependencies for security patches"
      ]
    },
    {
      "version": "1.1.0",
      "release_date": "2024-02-01",
      "changes": "Version 1.1.0 - Performance Improvements",
      "features": [
        "Database indexing optimization",
        "API response caching"
      ],
      "bugfixes": [
        "Fixed concurrent request handling"
      ],
      "security": []
    },
    {
      "version": "1.0.0",
      "release_date": "2024-01-15",
      "changes": "Version 1.0.0 - Initial Release",
      "features": [
        "Core POS functionality",
        "User management",
        "Product catalog"
      ],
      "bugfixes": [],
      "security": []
    }
  ]
}
```

**Response** (404 Not Found - Specific Version):
```json
{
  "success": false,
  "message": "Release notes for version 0.9.0 not found"
}
```

**Use Case**: Display changelog in Electron app's about/updates dialog.

---

## 5. Clean JSON Responses

All API responses follow a standardized format for consistency.

### Success Response Format
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    "id": 1,
    "name": "John Doe",
    ...
  }
}
```

**Status Codes**:
- `200 OK` - Successful GET/PATCH/PUT/DELETE
- `201 Created` - Successful POST
- `204 No Content` - Successful DELETE (empty response)

### Error Response Format
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field1": ["Error message 1", "Error message 2"],
    "field2": ["Error message"]
  }
}
```

**Status Codes**:
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Missing or invalid authentication
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `409 Conflict` - Resource conflict (duplicate, etc.)
- `422 Unprocessable Entity` - Validation errors
- `429 Too Many Requests` - Rate limit exceeded
- `500+ Server Error` - Internal server error

### Response Headers
All responses include:
```
Content-Type: application/json
X-Content-Type-Options: nosniff
Cache-Control: no-cache (for sensitive endpoints)
```

### BaseController Helper Methods
The [BaseController](app/Http/Controllers/API/BaseController.php) provides convenience methods:

```php
// Success responses
return $this->success($data, 'User created', 201);

// Error responses
return $this->error('Invalid input', 400);
return $this->badRequest('Validation failed', $errors);
return $this->unauthorized('Invalid credentials');
return $this->forbidden('You do not have permission');
return $this->notFound('Resource not found');
return $this->conflict('Resource already exists');
return $this->validationError($errors, 'Validation failed');
```

---

## 6. Electron App Integration Example

### 1. Initialize Authentication
```javascript
// In Electron main process or preload script
async function loginToBackend(email, password) {
  const response = await fetch('http://localhost:8000/api/v1/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  
  const data = await response.json();
  
  if (data.success) {
    // Store token securely
    return data.data.token; // Store in secure storage
  }
}
```

### 2. Check Server Health
```javascript
async function checkServerHealth() {
  try {
    const response = await fetch('http://localhost:8000/api/v1/health');
    const health = await response.json();
    
    if (health.status === 'healthy') {
      console.log('Server is ready');
      return true;
    } else {
      console.log('Server is degraded');
      return false;
    }
  } catch (error) {
    console.log('Server is unavailable');
    return false;
  }
}
```

### 3. Check for Updates
```javascript
async function checkForUpdates() {
  const currentVersion = '1.0.0';
  
  const response = await fetch(
    `http://localhost:8000/api/v1/version/compatibility?client=electron&version=${currentVersion}`
  );
  
  const check = await response.json();
  
  if (!check.compatible) {
    // Prompt user to upgrade
    console.log(`Update required: ${check.message}`);
    // Open download link
  }
}
```

### 4. Make Authenticated Requests
```javascript
async function makeAuthenticatedRequest(endpoint, token) {
  const response = await fetch(`http://localhost:8000/api/v1${endpoint}`, {
    method: 'GET',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    }
  });
  
  return response.json();
}
```

---

## 7. Security Considerations

### Token Storage in Electron
- **Never** store tokens in plain text
- Use Electron's `safeStorage` API or system keychain
- Clear tokens on logout
- Implement token refresh before expiration

### CORS & Origins
- Only configured origins can access API
- file:// protocol origins restricted to app://*
- Add production origin when deploying

### Rate Limiting
- API implements rate limiting (see [SECURITY_IMPLEMENTATION_SUMMARY.md](SECURITY_IMPLEMENTATION_SUMMARY.md))
- Monitor X-RateLimit headers in response
- Implement exponential backoff for retries

### Audit Logging
- Sensitive operations are logged
- Attempt to bypass security will be recorded
- Review audit logs regularly

---

## 8. Troubleshooting

### CORS Errors
**Problem**: `No 'Access-Control-Allow-Origin' header`

**Solution**:
1. Verify origin in [config/cors.php](config/cors.php)
2. Check if it matches configured origins
3. Clear browser cache
4. Verify CORS middleware is enabled

### Token Expired
**Problem**: `401 Unauthorized` responses

**Solution**:
1. Token expires after 30 days
2. Implement token refresh before expiration
3. Re-authenticate on 401 response
4. Store new token securely

### Health Check Fails
**Problem**: Health endpoint returns 503 Service Unavailable

**Solution**:
1. Check database connection
2. Check cache/Redis connection
3. Review application logs in `storage/logs/`
4. Verify database migrations are run

### Incompatible Version
**Problem**: `426 Upgrade Required` response

**Solution**:
1. Check current Electron app version
2. Compare with required version from `/api/v1/version/compatibility`
3. Download and install latest version
4. Restart Electron application

---

## 9. Configuration Files

### CORS Configuration
- **File**: [config/cors.php](config/cors.php)
- **Key Settings**:
  - `paths`: API routes that support CORS
  - `allowed_origins`: Electron origins
  - `allowed_origins_patterns`: Regex patterns
  - `allowed_methods`: HTTP methods
  - `exposed_headers`: Headers visible to client

### Sanctum Configuration
- **File**: [config/sanctum.php](config/sanctum.php)
- **Key Settings**:
  - `stateful`: Domains for cookie-based auth
  - `expiration`: Token lifetime in minutes (30 days = 43200)
  - `middleware`: Auth middleware configuration

---

## 10. Testing Endpoints with cURL

### Test Health Endpoint
```bash
curl -i http://localhost:8000/api/v1/health
```

### Test Version Endpoint
```bash
curl -i http://localhost:8000/api/v1/version
```

### Test Compatibility Check
```bash
curl -i "http://localhost:8000/api/v1/version/compatibility?client=electron&version=1.0.0"
```

### Test Changelog
```bash
curl -i http://localhost:8000/api/v1/version/changelog?limit=5
```

### Test Authenticated Request
```bash
curl -i -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/v1/auth/me
```

---

## 11. Performance Optimization

### Caching
- Health endpoints use `Cache-Control: no-cache`
- Version endpoints cache information for 1 hour
- Changelog caches for 24 hours

### Connection Pooling
- Use persistent connections in Electron app
- Implement request batching when possible
- Consider WebSocket for real-time updates

### Response Size
- Electron receives minimal response payloads
- Use `detailed` endpoints only when necessary
- Paginate large result sets

---

## Summary

The POS API is fully configured for Electron desktop integration:
- ✅ CORS configured for all Electron origins
- ✅ Tokens optimized for long desktop sessions (30 days)
- ✅ Health endpoints for monitoring
- ✅ Version endpoints for update management
- ✅ Clean JSON responses across all endpoints
- ✅ Security hardened with rate limiting and audit logging

For questions or issues, refer to the security documentation in [SECURITY_IMPLEMENTATION_SUMMARY.md](SECURITY_IMPLEMENTATION_SUMMARY.md).
