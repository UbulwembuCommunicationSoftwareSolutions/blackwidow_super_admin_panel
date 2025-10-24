# User Sync API Endpoints Documentation

## Overview

This document describes the three main API endpoints that handle user synchronization from external systems (CMS) to the SuperAdmin system. All endpoints use `app_url` to identify the customer and automatically trigger user imports to customer subscriptions after each operation.

---

## 🔑 Key Principles

### Password Handling

-   **CMS always sends CLEARTEXT passwords**
-   **SuperAdmin is responsible for hashing passwords**
-   **All responses include the hashed password**
-   **Passwords are never logged in cleartext**

### Customer Identification

-   **`app_url` is used to identify the customer**
-   **URLs are cleaned by removing http/https protocols and trailing slashes**
-   **Customer lookup uses LIKE comparison for flexible matching**
-   **All operations are scoped to the customer's subscription**

#### URL Cleaning Process

The system automatically cleans the `app_url` before customer lookup:

```php
// Input examples:
"https://cms.blackwidow.org.za"     → "cms.blackwidow.org.za"
"http://cms.blackwidow.org.za/"     → "cms.blackwidow.org.za"
"https://cms.blackwidow.org.za/"    → "cms.blackwidow.org.za"

// Database lookup uses LIKE comparison:
WHERE url LIKE '%cms.blackwidow.org.za%'
```

This ensures flexible matching regardless of protocol or trailing slashes.

### Automatic Sync Triggers

-   **All endpoints automatically trigger user imports to customer subscriptions**
-   **Uses `StartUserSyncJob` to sync users to all customer subscriptions**

---

## 1. Create User Endpoint

### Endpoint

```
POST /api/create-user
```

### Purpose

Creates a new user in the SuperAdmin system when a user is created in the CMS.

### Request Format

```json
{
    "app_url": "https://cms.blackwidow.org.za",
    "subscription_id": "uuid-string",
    "password": "MyPassword123",
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

### Request Validation

-   `app_url`: Required string - identifies the customer
-   `subscription_id`: Required string - UUID of the subscription
-   `password`: Required string - cleartext password (will be hashed)
-   `user.email`: Required email - must be unique for the customer
-   `user.first_name`: Required string
-   All other user fields are optional

### Response Format

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
        "password": "$2y$12$abc123...",
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

### Error Responses

```json
{
    "success": false,
    "message": "Email already exists",
    "errors": {
        "email": ["The email has already been taken."]
    }
}
```

### What Happens

1. ✅ Validates request data
2. ✅ Finds customer by `app_url`
3. ✅ Checks for duplicate email
4. ✅ Creates user with hashed password
5. ✅ Sets access permissions based on subscription
6. ✅ **Triggers user import to customer subscriptions**
7. ✅ Returns user data with SuperAdmin ID

---

## 2. Update User Endpoint

### Endpoint

```
POST /api/update-user
```

### Purpose

Updates an existing user in the SuperAdmin system when a user is modified in the CMS. Includes conflict resolution based on timestamps.

### Request Format

```json
{
    "app_url": "https://cms.blackwidow.org.za",
    "super_admin_user_id": 456,
    "email": "john@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "cellphone": "+27821234567",
    "password": "NewPassword456",
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

### Request Validation

-   `app_url`: Required string - identifies the customer
-   `super_admin_user_id`: Required integer - SuperAdmin's user ID
-   `email`: Required email
-   `first_name`: Required string
-   `password`: Optional string - cleartext password (will be hashed if provided)
-   `cms_updated_at`: Optional date - for conflict resolution
-   All other fields are optional

### Response Format (Successful Update)

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
        "password": "$2y$12$xyz789...",
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

### Response Format (Conflict Resolution)

```json
{
    "success": true,
    "message": "User updated successfully (conflict resolved - SuperAdmin version is newer)",
    "user": {
        "id": 456,
        "email_address": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "cellphone": "+27821234567",
        "password": "$2y$12$abc123...",
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
        "updated_at": "2025-10-23T15:40:00.000000Z"
    }
}
```

### Error Responses

```json
{
    "success": false,
    "message": "User not found",
    "error": "No user found with super_admin_user_id: 456"
}
```

### What Happens

1. ✅ Validates request data
2. ✅ Finds customer by `app_url`
3. ✅ Finds user by `super_admin_user_id`
4. ✅ **Conflict Resolution**: Compares timestamps if `cms_updated_at` provided
5. ✅ If SuperAdmin version is newer: Returns SuperAdmin's data
6. ✅ If CMS version is newer: Updates user with CMS data
7. ✅ Hashes password if provided
8. ✅ **Triggers user import to customer subscriptions**
9. ✅ Returns updated user data

---

## 3. Password Update Endpoint

### Endpoint

```
POST /api/update-password
```

### Purpose

Updates a user's password in the SuperAdmin system when a password is changed in the CMS.

### Request Format

```json
{
    "app_url": "https://cms.blackwidow.org.za",
    "super_admin_user_id": 456,
    "password": "NewSecurePassword123",
    "email": "john@example.com",
    "cellphone": "+27821234567"
}
```

### Request Validation

-   `app_url`: Required string - identifies the customer
-   `super_admin_user_id`: Required integer - SuperAdmin's user ID
-   `password`: Required string - minimum 6 characters, cleartext (will be hashed)
-   `email`: Optional email - can be updated along with password
-   `cellphone`: Optional string - can be updated along with password

### Response Format

```json
{
    "success": true,
    "message": "Password updated successfully",
    "user": {
        "id": 456,
        "email_address": "john@example.com",
        "first_name": "John",
        "last_name": "Doe",
        "cellphone": "+27821234567",
        "updated_at": "2025-10-23T15:35:00.000000Z"
    }
}
```

### Error Responses

```json
{
    "success": false,
    "message": "User not found",
    "error": "No user found with super_admin_user_id: 456"
}
```

### What Happens

1. ✅ Validates request data
2. ✅ Finds customer by `app_url`
3. ✅ Finds user by `super_admin_user_id`
4. ✅ Updates password (automatically hashed by model)
5. ✅ Updates email/cellphone if provided
6. ✅ **Triggers user import to customer subscriptions**
7. ✅ Returns updated user data

---

## 🔄 Automatic Sync Process

### What Triggers User Import

All three endpoints automatically trigger user imports to customer subscriptions using:

```php
StartUserSyncJob::dispatch($user->customer_id);
```

### What This Does

1. **Finds all customer subscriptions** for the customer
2. **Calls CMSService::syncUsers()** for each subscription
3. **Sends POST request** to `{subscription_url}/admin-api/sync-users`
4. **Uses customer token** for authentication
5. **Logs all operations** for monitoring

### Sync Flow

```
User Operation (Create/Update/Password)
    ↓
SuperAdmin processes request
    ↓
StartUserSyncJob dispatched
    ↓
CMSService::syncUsers() called
    ↓
POST to {app_url}/admin-api/sync-users
    ↓
CMS imports updated user data
    ↓
Both systems synchronized ✅
```

---

## 🧪 Testing Examples

### Test 1: Create User

```bash
curl -X POST https://superadmin.blackwidow.org.za/api/create-user \
  -H "Content-Type: application/json" \
  -d '{
    "app_url": "https://cms.blackwidow.org.za",
    "subscription_id": "uuid-string",
    "password": "TestPassword123",
    "user": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "cellphone": "+27821234567",
      "active": true,
      "console_access": true
    }
  }'
```

### Test 2: Update User

```bash
curl -X POST https://superadmin.blackwidow.org.za/api/update-user \
  -H "Content-Type: application/json" \
  -d '{
    "app_url": "https://cms.blackwidow.org.za",
    "super_admin_user_id": 456,
    "email": "john@example.com",
    "first_name": "John Updated",
    "password": "NewPassword456",
    "console_access": true,
    "cms_updated_at": "2025-10-23T15:30:00.000000Z"
  }'
```

### Test 3: Update Password

```bash
curl -X POST https://superadmin.blackwidow.org.za/api/update-password \
  -H "Content-Type: application/json" \
  -d '{
    "app_url": "https://cms.blackwidow.org.za",
    "super_admin_user_id": 456,
    "password": "NewSecurePassword789"
  }'
```

---

## 🔒 Security Considerations

### Password Security

-   ✅ **HTTPS encryption** - All traffic encrypted in transit
-   ✅ **No cleartext storage** - Passwords immediately hashed
-   ✅ **No cleartext logging** - Only hashed passwords logged
-   ✅ **bcrypt hashing** - Industry standard password hashing

### Authentication

-   ✅ **App URL validation** - Ensures requests are from valid customers
-   ✅ **Customer scoping** - All operations scoped to specific customer
-   ✅ **User ID validation** - Prevents cross-customer user access

### Error Handling

-   ✅ **No sensitive data in errors** - Passwords never exposed in error messages
-   ✅ **Detailed logging** - All operations logged for audit
-   ✅ **Graceful failures** - Proper error responses without system details

---

## 📊 Monitoring & Logging

### What Gets Logged

-   ✅ **All API requests** - Complete request data (except passwords)
-   ✅ **User operations** - Create, update, password change operations
-   ✅ **Sync triggers** - When user imports are triggered
-   ✅ **Error conditions** - Failed operations with error details

### Log Examples

```
[INFO] Create user from CMS: {"app_url":"https://cms.blackwidow.org.za","user":{"email":"john@example.com"}}
[INFO] User created successfully: john@example.com
[INFO] Update user from CMS: {"app_url":"https://cms.blackwidow.org.za","super_admin_user_id":456}
[INFO] User updated successfully: john@example.com
[INFO] Update password from CMS: {"app_url":"https://cms.blackwidow.org.za","super_admin_user_id":456}
[INFO] Password updated successfully for user: john@example.com
```

---

## ✅ Implementation Status

-   [x] **Create User Endpoint** - Fully implemented with password hashing and sync triggers
-   [x] **Update User Endpoint** - Fully implemented with conflict resolution and sync triggers
-   [x] **Password Update Endpoint** - Fully implemented with sync triggers
-   [x] **Automatic Sync Triggers** - All endpoints trigger user imports
-   [x] **Password Security** - Cleartext received, hashed stored, hashed returned
-   [x] **Error Handling** - Comprehensive validation and error responses
-   [x] **Logging** - Complete audit trail for all operations
-   [x] **Documentation** - Complete API documentation

---

**Document Version:** 1.0  
**Last Updated:** October 23, 2025  
**Status:** ✅ Production Ready
