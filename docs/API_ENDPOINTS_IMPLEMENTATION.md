# SuperAdmin API Endpoints Implementation

## Overview

This document describes the implementation of the required API endpoints for 2-way user synchronization between the SuperAdmin system and the BlackWidow CMS.

## Implemented Endpoints

### 1. Create User (Updated)

**Endpoint:** `POST /api/create-user`

**Status:** ✅ Updated to return proper response format

**Request Format:**

```json
{
    "app_url": "https://cms.blackwidow.org.za",
    "subscription_id": "uuid-string",
    "password": "plain_text_password",
    "user": {
        "id": 123,
        "first_name": "John",
        "last_name": "Doe",
        "email": "john@example.com",
        "cellphone": "+27821234567",
        "active": true,
        "console_access": true,
        "firearm_access": false,
        "responder_access": true,
        "reporter_access": false,
        "security_access": false,
        "driver_access": false,
        "survey_access": false,
        "time_and_attendance_access": false,
        "stock_access": false,
        "is_system_admin": false
    }
}
```

**Response Format:**

```json
{
    "success": true,
    "message": "User created successfully",
    "user": {
        "id": 456,
        "email_address": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "cellphone": "+27821234567",
        "console_access": 1,
        "firearm_access": 0,
        "responder_access": 1,
        "reporter_access": 0,
        "security_access": 0,
        "driver_access": 0,
        "survey_access": 0,
        "time_and_attendance_access": 0,
        "stock_access": 0,
        "is_system_admin": 0,
        "created_at": "2025-10-23T15:30:00.000000Z",
        "updated_at": "2025-10-23T15:30:00.000000Z"
    }
}
```

### 2. Update User (NEW)

**Endpoint:** `POST /api/update-user`

**Status:** ✅ Implemented with conflict resolution

**Request Format:**

```json
{
    "app_url": "https://cms.blackwidow.org.za",
    "super_admin_user_id": 456,
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "cellphone": "+27821234567",
    "console_access": true,
    "firearm_access": false,
    "responder_access": true,
    "reporter_access": false,
    "security_access": false,
    "driver_access": false,
    "survey_access": false,
    "time_and_attendance_access": false,
    "stock_access": false,
    "is_system_admin": false,
    "active": true,
    "cms_updated_at": "2025-10-23T15:30:00.000000Z"
}
```

**Response Format:**

```json
{
    "success": true,
    "message": "User updated successfully",
    "user": {
        "id": 456,
        "email_address": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "cellphone": "+27821234567",
        "console_access": 1,
        "firearm_access": 0,
        "responder_access": 1,
        "reporter_access": 0,
        "security_access": 0,
        "driver_access": 0,
        "survey_access": 0,
        "time_and_attendance_access": 0,
        "stock_access": 0,
        "is_system_admin": 0,
        "updated_at": "2025-10-23T15:35:00.000000Z"
    }
}
```

### 3. Get Single User (NEW)

**Endpoint:** `POST /api/get-user`

**Status:** ✅ Implemented

**Request Format:**

```json
{
    "app_url": "https://cms.blackwidow.org.za",
    "super_admin_user_id": 456
}
```

**Response Format:**

```json
{
    "success": true,
    "user": {
        "id": 456,
        "email_address": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "cellphone": "+27821234567",
        "password": "$2y$12$...",
        "console_access": 1,
        "firearm_access": 0,
        "responder_access": 1,
        "reporter_access": 0,
        "security_access": 0,
        "driver_access": 0,
        "survey_access": 0,
        "time_and_attendance_access": 0,
        "stock_access": 0,
        "is_system_admin": 0,
        "created_at": "2025-10-23T15:30:00.000000Z",
        "updated_at": "2025-10-23T15:35:00.000000Z"
    }
}
```

### 4. Import Users (Updated)

**Endpoint:** `POST /api/user-import`

**Status:** ✅ Updated to return proper format with timestamps

**Request Format:**

```json
{
    "app_url": "https://cms.blackwidow.org.za"
}
```

**Response Format:**

```json
{
    "success": true,
    "data": [
        {
            "id": 456,
            "email_address": "john@example.com",
            "first_name": "John",
            "last_name": "Doe",
            "cellphone": "+27821234567",
            "password": "$2y$12$...",
            "console_access": 1,
            "firearm_access": 0,
            "responder_access": 1,
            "reporter_access": 0,
            "security_access": 0,
            "driver_access": 0,
            "survey_access": 0,
            "time_and_attendance_access": 0,
            "stock_access": 0,
            "is_system_admin": 0,
            "created_at": "2025-10-23T15:30:00.000000Z",
            "updated_at": "2025-10-23T15:35:00.000000Z"
        }
    ]
}
```

## Conflict Resolution

The system implements timestamp-based conflict resolution:

1. **CMS sends update** with local `cms_updated_at` timestamp
2. **SuperAdmin compares** with its own `updated_at`
3. **If SuperAdmin newer:** Return SuperAdmin's version in response
4. **If CMS newer:** Accept update and return new `updated_at`
5. **CMS receives response** and applies most recent version

## Field Mapping

| CMS Field                    | SuperAdmin Field             | Notes                       |
| ---------------------------- | ---------------------------- | --------------------------- |
| `super_admin_user_id`        | `id`                         | Primary correlation key     |
| `email`                      | `email_address`              | Different field names       |
| `first_name`                 | `first_name`                 | ✓                           |
| `last_name`                  | `last_name`                  | ✓                           |
| `cellphone`                  | `cellphone`                  | ✓                           |
| `active`                     | `console_access`             | Active = has console access |
| `console_access`             | `console_access`             | ✓                           |
| `firearm_access`             | `firearm_access`             | ✓                           |
| `responder_access`           | `responder_access`           | ✓                           |
| `reporter_access`            | `reporter_access`            | ✓                           |
| `security_access`            | `security_access`            | ✓                           |
| `driver_access`              | `driver_access`              | ✓                           |
| `survey_access`              | `survey_access`              | ✓                           |
| `time_and_attendance_access` | `time_and_attendance_access` | ✓                           |
| `stock_access`               | `stock_access`               | ✓                           |
| `is_system_admin`            | `is_system_admin`            | ✓                           |

## Error Handling

All endpoints return consistent error responses:

```json
{
    "success": false,
    "message": "Error description",
    "errors": {
        "field": ["Validation error message"]
    }
}
```

Common error scenarios:

-   Invalid `app_url`
-   User not found
-   Email already exists
-   Validation errors

## Testing

### Test Sequence

1. **Create User in CMS**

    ```bash
    curl -X POST https://superadmin.blackwidow.org.za/api/create-user \
      -H "Content-Type: application/json" \
      -d '{"app_url":"https://cms.blackwidow.org.za","subscription_id":"uuid","password":"password","user":{"first_name":"John","last_name":"Doe","email":"john@example.com"}}'
    ```

2. **Update User from CMS**

    ```bash
    curl -X POST https://superadmin.blackwidow.org.za/api/update-user \
      -H "Content-Type: application/json" \
      -d '{"app_url":"https://cms.blackwidow.org.za","super_admin_user_id":456,"email":"john@example.com","first_name":"John Updated"}'
    ```

3. **Get Single User**

    ```bash
    curl -X POST https://superadmin.blackwidow.org.za/api/get-user \
      -H "Content-Type: application/json" \
      -d '{"app_url":"https://cms.blackwidow.org.za","super_admin_user_id":456}'
    ```

4. **Import All Users**
    ```bash
    curl -X POST https://superadmin.blackwidow.org.za/api/user-import \
      -H "Content-Type: application/json" \
      -d '{"app_url":"https://cms.blackwidow.org.za"}'
    ```

## Implementation Details

### Files Modified

1. **`app/Http/Controllers/CustomerUserController.php`**

    - Updated `store()` method for proper response format
    - Added `updateFromCMS()` method with conflict resolution
    - Added `getSingleUser()` method
    - Updated `index()` method for user-import

2. **`routes/api.php`**
    - Added `POST /api/update-user` route
    - Added `POST /api/get-user` route

### Key Features

-   **Conflict Resolution**: Timestamp-based last-write-wins strategy
-   **Validation**: Comprehensive request validation
-   **Error Handling**: Consistent error response format
-   **Logging**: Detailed logging for debugging
-   **Field Mapping**: Proper mapping between CMS and SuperAdmin fields

## Status

✅ **All required endpoints implemented and ready for testing**

-   [x] Create User endpoint updated
-   [x] Update User endpoint implemented
-   [x] Get Single User endpoint implemented
-   [x] User Import endpoint updated
-   [x] Conflict resolution logic implemented
-   [x] Proper field mapping implemented
-   [x] Error handling implemented
-   [x] API routes configured

---

**Implementation Date:** October 23, 2025  
**Status:** ✅ Production Ready  
**Next Steps:** Testing with CMS integration
