# Create User API — For External Platforms

This document describes how other platforms can create users in the Super Admin panel by calling the existing create-user endpoint. The customer (tenant) is identified by **app_url** only.

**Base URL:** `https://superadmin.blackwidow.org.za`

---

## Endpoint

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/create-user` | Create a new user for a customer identified by `app_url` |

**Full URL:** `https://superadmin.blackwidow.org.za/api/create-user`

---

## Request

Send a JSON body with `Content-Type: application/json`.

### Required fields

| Field | Type | Description |
|-------|------|-------------|
| `app_url` | string | Customer subscription URL (e.g. `https://cms.blackwidow.org.za`). Used to identify the customer. |
| `password` | string | User’s plain-text password (stored hashed by the server). |
| `user` | object | User payload (see below). |
| `user.first_name` | string | User’s first name. |
| `user.email` | string | User’s email (must be unique per customer). |

### Optional fields (inside `user`)

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `user.last_name` | string | `null` | User’s last name. |
| `user.cellphone` | string | `null` | User’s cellphone. |
| `user.active` | boolean | — | If set, used as fallback for `console_access` when platform flags are omitted. |
| `user.console_access` | boolean | `user.active` or `true` | Access to console platform. |
| `user.firearm_access` | boolean | `false` | Access to firearm platform. |
| `user.responder_access` | boolean | `false` | Access to responder platform. |
| `user.reporter_access` | boolean | `false` | Access to reporter platform. |
| `user.security_access` | boolean | `false` | Access to security platform. |
| `user.driver_access` | boolean | `false` | Access to driver platform. |
| `user.survey_access` | boolean | `false` | Access to survey platform. |
| `user.time_and_attendance_access` | boolean | `false` | Access to time & attendance platform. |
| `user.stock_access` | boolean | `false` | Access to stock platform. |
| `user.is_system_admin` | boolean | `false` | System admin flag. |

The top-level field `subscription_id` is accepted but **ignored**; only `app_url` is used to find the customer.

---

## Example request body

```json
{
    "app_url": "https://cms.blackwidow.org.za",
    "subscription_id": "optional-ignored",
    "password": "SecurePassword123",
    "user": {
        "first_name": "Jane",
        "last_name": "Doe",
        "email": "jane.doe@example.com",
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

---

## Success response

**Status:** `200 OK`

```json
{
    "success": true,
    "message": "User created successfully",
    "user": {
        "id": 456,
        "email_address": "jane.doe@example.com",
        "first_name": "Jane",
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
        "created_at": "2025-02-12T10:30:00.000000Z",
        "updated_at": "2025-02-12T10:30:00.000000Z"
    }
}
```

Use the returned `user.id` as the Super Admin user ID (e.g. for update/get calls). The returned `password` is the stored hash; do not use it as the user’s login password.

---

## Error responses

### Invalid app URL (customer not found)

**Status:** `400 Bad Request`

```json
{
    "success": false,
    "message": "Invalid app URL"
}
```

### Email already exists for this customer

**Status:** `422 Unprocessable Entity`

```json
{
    "success": false,
    "message": "Email already exists",
    "errors": {
        "email": ["The email has already been taken."]
    }
}
```

### Validation errors (missing/invalid fields)

**Status:** `422 Unprocessable Entity`

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "user.first_name": ["The user.first name field is required."],
        "user.email": ["The user.email field must be a valid email address."]
    }
}
```

### Server error

**Status:** `500 Internal Server Error`

```json
{
    "success": false,
    "message": "Failed to create user: <detail>"
}
```

---

## cURL example

```bash
curl -X POST https://superadmin.blackwidow.org.za/api/create-user \
  -H "Content-Type: application/json" \
  -d '{
    "app_url": "https://cms.blackwidow.org.za",
    "password": "SecurePassword123",
    "user": {
      "first_name": "Jane",
      "last_name": "Doe",
      "email": "jane.doe@example.com",
      "cellphone": "+27821234567",
      "console_access": true,
      "responder_access": true
    }
  }'
```

---

## Platform access summary

Access to each platform is controlled by the corresponding boolean in `user`:

- **console_access** — Console
- **firearm_access** — Firearm
- **responder_access** — Responder
- **reporter_access** — Reporter
- **security_access** — Security
- **driver_access** — Driver
- **survey_access** — Survey
- **time_and_attendance_access** — Time & Attendance
- **stock_access** — Stock

Omitting a flag defaults it to `false`, except `console_access`, which defaults to `user.active` if present, otherwise `true`. After creation, the user is granted access to the subscription that matches the given `app_url` according to the server’s rules.
