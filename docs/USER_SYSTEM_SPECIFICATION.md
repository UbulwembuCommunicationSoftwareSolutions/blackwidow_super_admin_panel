# Universal User System Specification

## Overview
This document defines the universal user system structure for the BlackWidow Super Admin Panel. This specification can be used to synchronize user data across multiple systems and platforms.

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_address VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    cellphone VARCHAR(255) NULL,
    console_access TINYINT(1) NOT NULL DEFAULT 0,
    firearm_access TINYINT(1) NOT NULL DEFAULT 0,
    responder_access TINYINT(1) NOT NULL DEFAULT 0,
    reporter_access TINYINT(1) NOT NULL DEFAULT 0,
    security_access TINYINT(1) NOT NULL DEFAULT 0,
    driver_access TINYINT(1) NOT NULL DEFAULT 0,
    survey_access TINYINT(1) NOT NULL DEFAULT 0,
    time_and_attendance_access TINYINT(1) NOT NULL DEFAULT 0,
    stock_access TINYINT(1) NOT NULL DEFAULT 0,
    is_system_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

## Field Definitions

### Core User Fields
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `id` | BIGINT | Yes | Unique identifier (auto-increment) |
| `email_address` | VARCHAR(255) | Yes | User's email address (unique) |
| `password` | VARCHAR(255) | Yes | Hashed password using Laravel Hash |
| `first_name` | VARCHAR(255) | Yes | User's first name |
| `last_name` | VARCHAR(255) | Yes | User's last name |
| `cellphone` | VARCHAR(255) | No | User's phone number |

### Access Control Fields
| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `console_access` | TINYINT(1) | 0 | Access to console management |
| `firearm_access` | TINYINT(1) | 0 | Access to firearm management |
| `responder_access` | TINYINT(1) | 0 | Access to responder management |
| `reporter_access` | TINYINT(1) | 0 | Access to reporting features |
| `security_access` | TINYINT(1) | 0 | Access to security features |
| `driver_access` | TINYINT(1) | 0 | Access to driver management |
| `survey_access` | TINYINT(1) | 0 | Access to survey features |
| `time_and_attendance_access` | TINYINT(1) | 0 | Access to time tracking |
| `stock_access` | TINYINT(1) | 0 | Access to stock management |
| `is_system_admin` | TINYINT(1) | 0 | Full system administrator access |

## Subscription Type Mapping

### Access Control Matrix
| Subscription Type ID | Name | Required Access Field |
|---------------------|------|---------------------|
| 1 | Console | `console_access` |
| 2 | Firearm | `firearm_access` |
| 3 | Responder | `responder_access` |
| 4 | Reporter | `reporter_access` |
| 5 | Security | `security_access` |
| 6 | Driver | `driver_access` |
| 7 | Survey | `survey_access` |
| 9 | Time & Attendance | `time_and_attendance_access` |
| 10 | Stock | `stock_access` |

## API Endpoints

### Authentication
- `POST /api/users/login` - User login
- `POST /api/users/update-password` - Update password
- `POST /api/users/activate` - Activate user
- `POST /api/users/deactivate` - Deactivate user

### User Management
- `GET /api/users` - List users
- `POST /api/users` - Create new user
- `GET /api/users/{id}` - Get user details
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

## Data Validation Rules

### User Creation
```php
[
    'email_address' => 'required|email|unique:users,email_address',
    'password' => 'required|min:6',
    'first_name' => 'required|string|max:255',
    'last_name' => 'required|string|max:255',
    'cellphone' => 'nullable|string|max:255',
    'console_access' => 'boolean',
    'firearm_access' => 'boolean',
    'responder_access' => 'boolean',
    'reporter_access' => 'boolean',
    'security_access' => 'boolean',
    'driver_access' => 'boolean',
    'survey_access' => 'boolean',
    'time_and_attendance_access' => 'boolean',
    'stock_access' => 'boolean',
    'is_system_admin' => 'boolean'
]
```

## Authentication Flow

### Login Process
1. Find user by email or cellphone
2. Verify password using Hash::check()
3. Check access permissions based on subscription type
4. Generate Sanctum token for API access
5. Return user data and token

### Access Control Logic
```php
public function checkAccess($subscription_type_id) : bool
{
    $accessTypes = [
        'console_access' => 1,
        'firearm_access' => 2,
        'responder_access' => 3,
        'reporter_access' => 4,
        'security_access' => 5,
        'driver_access' => 6,
        'survey_access' => 7,
        'time_and_attendance_access' => 9,
        'stock_access' => 10,
    ];

    foreach ($accessTypes as $access => $typeId) {
        if ($subscription_type_id == $typeId && $this->$access) {
            return true;
        }
    }

    return false;
}
```

## Integration Guidelines

### For External Systems

#### 1. User Synchronization
- Implement webhook endpoints for user CRUD operations
- Use standardized JSON format for user data
- Include access control fields in all user operations

#### 2. Authentication Integration
- Implement OAuth2 or API token authentication
- Validate user access based on subscription types
- Use consistent error response format

#### 3. Data Format
```json
{
    "user": {
        "id": 1,
        "email_address": "user@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "cellphone": "+1234567890",
        "access_controls": {
            "console_access": true,
            "firearm_access": false,
            "responder_access": false,
            "reporter_access": true,
            "security_access": false,
            "driver_access": false,
            "survey_access": false,
            "time_and_attendance_access": false,
            "stock_access": false
        },
        "is_system_admin": false,
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-01-01T00:00:00Z"
    }
}
```

## Security Considerations

### Password Handling
- Always hash passwords using Laravel's Hash::make()
- Never store plain text passwords
- Use secure password validation rules

### Access Control
- Validate user permissions on every API request
- Implement role-based access control (RBAC)
- Log all authentication attempts and access changes

### Data Protection
- Encrypt sensitive user data at rest
- Use HTTPS for all API communications
- Implement rate limiting on authentication endpoints

## Migration Scripts

### Example Migration for New System
```php
// Create users table
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email_address');
    $table->string('password');
    $table->string('first_name');
    $table->string('last_name');
    $table->string('cellphone')->nullable();
    
    // Access control fields
    $table->boolean('console_access')->default(false);
    $table->boolean('firearm_access')->default(false);
    $table->boolean('responder_access')->default(false);
    $table->boolean('reporter_access')->default(false);
    $table->boolean('security_access')->default(false);
    $table->boolean('driver_access')->default(false);
    $table->boolean('survey_access')->default(false);
    $table->boolean('time_and_attendance_access')->default(false);
    $table->boolean('stock_access')->default(false);
    $table->boolean('is_system_admin')->default(false);
    
    $table->timestamps();
    
    $table->unique('email_address');
});
```

## Testing Guidelines

### Unit Tests
- Test user creation with all access control combinations
- Test authentication with valid/invalid credentials
- Test access control logic for each subscription type

### Integration Tests
- Test API endpoints with proper authentication
- Test user synchronization between systems
- Test error handling and validation

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2024-01-01 | Initial specification |
| 1.1 | 2024-01-15 | Added access control matrix |
| 1.2 | 2024-01-30 | Added security considerations |

## Support

For questions or clarifications about this specification, please refer to the main system documentation or contact the development team. 
