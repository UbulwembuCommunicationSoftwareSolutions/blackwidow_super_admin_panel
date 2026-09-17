# Tenant Sync Integration Guide

How another BlackWidow tenant app (CMS, Firearm, Responder, etc.) must implement **user sync** and **branding sync** with Super Admin.

This is the contract the panel already speaks. Mirror it; do not invent parallel endpoints.

---

## 1. Prerequisites (Super Admin side)

Before any sync works, the tenant must exist as a customer subscription:

| Field | Purpose |
| --- | --- |
| `customers.token` | Shared secret. Tenant stores it as `SECURE_TOKEN`. Used as Bearer both ways. |
| `customer_subscriptions.url` | Tenant base URL (e.g. `https://demo.example.test`). Used for `app_url` matching and outbound pushes. |
| `customer_subscriptions.subscription_type_id` | Which product this subscription is (console / firearm / …). |

Without a matching subscription + token, every call returns `401 Unauthorized`.

---

## 2. Authentication (both directions)

### Tenant → Super Admin

```
Authorization: Bearer {customers.token}
Content-Type: application/json
```

Every request body **must** include `app_url` set to the tenant’s public base URL (same value stored on `customer_subscriptions.url`). Protocol and trailing slash are normalized on the panel.

Base path:

```
{SUPERADMIN_API}/api/v1/sync/...
```

### Super Admin → Tenant

The panel POSTs to:

```
{tenant_url}/admin-api/v1/sync/...
Authorization: Bearer {customers.token}
```

The tenant **must**:

1. Expose `/admin-api/v1/sync/*` (no CSRF).
2. Reject requests whose Bearer token ≠ local `SECURE_TOKEN`.
3. Apply the payload with echo suppression (`skipSync` / equivalent) so applying an inbound write does **not** push the same change back.

---

## 3. Shared rules (users + branding)

| Rule | Detail |
| --- | --- |
| Canonical payloads | One wire shape both ways. Responses return the receiver’s authoritative copy. |
| Last-write-wins (LWW) | Compare `updated_at` (ISO-8601). Older inbound → outcome `stale`, keep local, return local. |
| Echo suppression | After applying inbound data, set a skip flag so observers / jobs do not re-push. |
| Queued outbound | Prefer a job with retries (e.g. 1m / 5m / 15m) for pushes to the other side. |
| Co-deploy | Both sides must ship matching `/v1/sync/*` routes together or you get `404`. |
| Outcomes | Responses include `outcome`: `created`, `updated`, `unchanged`, `stale`, `archived`, `restored`, `cleared` (branding). |

---

## 4. User sync

### Identity

Never match only on email for routine sync. Store both IDs on each side:

- `super_admin_user_id` → `customer_users.id` on the panel  
- `cms_user_id` → local `users.id` on the tenant  

When the panel creates a user, the tenant’s upsert response must return `cms_user_id` so the panel can store the link.

### Access flags

Boolean flags on the wire (map to subscription types on the panel):

| Flag | `subscription_type_id` |
| --- | --- |
| `console_access` | 1 |
| `firearm_access` | 2 |
| `responder_access` | 3 |
| `reporter_access` | 4 |
| `security_access` | 5 |
| `driver_access` | 6 |
| `survey_access` | 7 |
| `time_and_attendance_access` | 9 |
| `stock_access` | 10 |

Plus `is_system_admin`. Soft-delete / scheduled removal uses `delete_scheduled` (ISO-8601 or null).

### Passwords

- Tenant → panel: send **cleartext** in `password` when creating/changing. Panel hashes.
- Panel → tenant: send cleartext on password endpoint; tenant hashes with its own driver.
- Reconcile listing may include `password_hash` so a tenant can seed a user it has never seen. Do **not** overwrite an existing local password from a hash on every import.

### Canonical user payload

```json
{
  "super_admin_user_id": 456,
  "cms_user_id": 123,
  "email": "jane@example.com",
  "first_name": "Jane",
  "last_name": "Doe",
  "cellphone": "+27821234567",
  "console_access": true,
  "firearm_access": false,
  "responder_access": false,
  "reporter_access": false,
  "security_access": false,
  "driver_access": false,
  "survey_access": false,
  "time_and_attendance_access": false,
  "stock_access": false,
  "is_system_admin": false,
  "delete_scheduled": null,
  "updated_at": "2026-09-16T12:00:00+00:00"
}
```

### Endpoints the tenant must call (outbound)

| Method | Path | Body |
| --- | --- | --- |
| `GET` | `/api/v1/sync/users?app_url=...` | — (or `app_url` in query/body per client) |
| `POST` | `/api/v1/sync/users` | `{ "app_url", "user": {…}, "password"?: "…" }` |
| `POST` | `/api/v1/sync/users/archive` | `{ "app_url", "user": {…} }` |
| `POST` | `/api/v1/sync/users/restore` | `{ "app_url", "user": {…} }` |
| `POST` | `/api/v1/sync/users/password` | `{ "app_url", "user": {…}, "password": "cleartext" }` |

`GET users` is the reconcile / safety-net pull. Routine traffic is per-record `POST`.

### Endpoints the tenant must expose (inbound from panel)

| Method | Path |
| --- | --- |
| `POST` | `/admin-api/v1/sync/users` |
| `POST` | `/admin-api/v1/sync/users/archive` |
| `POST` | `/admin-api/v1/sync/users/restore` |
| `POST` | `/admin-api/v1/sync/users/password` |

Inbound body shape (example upsert):

```json
{
  "app_url": "https://tenant.example.test",
  "origin": "super_admin",
  "user": { "...canonical payload..." },
  "password": "optional-cleartext"
}
```

### Tenant implementation checklist (users)

1. Persist `super_admin_user_id` and `cms_user_id` (or equivalent columns).
2. On local create/update/archive/restore/password change → queue push to Super Admin (unless `skipSync`).
3. On inbound apply → LWW on `updated_at`, then `skipSync = true` before save.
4. Return canonical `user` + `outcome` on every write.
5. Optional: scheduled reconcile via `GET /api/v1/sync/users` (or legacy import) without stomping fresher local rows.

---

## 5. Branding sync

Logos only. Three slots on the wire (CMS names). Panel stores them as `logo_1` / `logo_2` / `logo_3`.

| Wire `slot` | Panel column | Typical use |
| --- | --- | --- |
| `login_logo` | `logo_1` | Login screen logo |
| `menu_logo` | `logo_2` | Sidebar / menu logo |
| `login_background` | `logo_3` | Login background |

### Canonical branding payload (one slot)

```json
{
  "slot": "login_logo",
  "url": "https://tenant.example.test/storage/logos/abc.png",
  "checksum": "sha256:deadbeef...",
  "cleared": false,
  "updated_at": "2026-09-16T12:00:00+00:00"
}
```

| Field | Rules |
| --- | --- |
| `slot` | Required; one of the three names above |
| `url` | Absolute, publicly downloadable URL when `cleared` is false |
| `checksum` | `sha256:` + hex SHA-256 of **file bytes**. Receiver downloads URL and verifies |
| `cleared` | `true` clears the slot (null path + null checksum); `url` may be null |
| `updated_at` | LWW clock for that slot |

### Conflict / no-op behaviour

1. Same checksum and same cleared state → `unchanged` (no write).
2. Incoming `updated_at` older than local → `stale` (keep local, return local).
3. Otherwise download (or clear), store bytes locally, stamp checksum + timestamp.

### Endpoints the tenant must call (outbound)

| Method | Path | Body |
| --- | --- | --- |
| `GET` | `/api/v1/sync/branding` | Requires `app_url` (query or body) |
| `POST` | `/api/v1/sync/branding` | `{ "app_url", "origin"?: "cms", "branding": {…} }` |

`GET` returns all three slots for reconcile. `POST` upserts one slot.

### Endpoints the tenant must expose (inbound)

| Method | Path |
| --- | --- |
| `POST` | `/admin-api/v1/sync/branding` |

Example inbound body:

```json
{
  "app_url": "https://tenant.example.test",
  "origin": "super_admin",
  "branding": {
    "slot": "menu_logo",
    "url": "https://superadmin.example/storage/uuid.png",
    "checksum": "sha256:…",
    "cleared": false,
    "updated_at": "2026-09-16T12:00:00+00:00"
  }
}
```

### Tenant implementation checklist (branding)

1. Store per-slot: file path (or null), `*_updated_at`, `*_checksum`.
2. On local upload/clear → compute checksum, stamp timestamp, queue push to Super Admin.
3. On inbound → LWW + checksum short-circuit; download URL to local disk; `skipSync` so observers do not echo.
4. URLs you publish must be reachable from Super Admin (and vice versa).
5. UI should fall back sensibly when a slot is empty.
6. Ship `/admin-api/v1/sync/branding` with the same release as the panel’s `/api/v1/sync/branding` (missing route = `404` on Resync).

---

## 6. Minimal “new app” build order

1. **Config** — `SECURE_TOKEN` = `customers.token`; `APP_URL` matches subscription URL; Super Admin API base URL.
2. **Auth** — Bearer check on all `/admin-api/v1/sync/*` Form Requests / middleware.
3. **Payload classes** — Copy / mirror `UserSyncPayload` and `BrandingSyncPayload` (same keys).
4. **Inbound controllers** — Upsert (+ archive/restore/password for users; branding upsert).
5. **Apply services** — LWW, checksum (branding), password hashing, `skipSync`.
6. **Outbound jobs** — Push on local change with retries.
7. **Reconcile** — Optional scheduled `GET` users / branding.
8. **Deploy** — Panel + tenant in the same window; then clear route/config cache on the tenant if needed.
9. **Verify** — Panel Cockpit → Branding / user resync; `php artisan route:list` on tenant shows `admin-api/v1/sync/...`.

---

## 7. What not to do

- Do not call legacy logo or user bulk endpoints for routine sync (`sync-logos`, ad-hoc `Mail::`, etc.).
- Do not match users only by email when both IDs exist.
- Do not push after applying an inbound write (echo loops).
- Do not send password hashes from tenant → panel on upsert (cleartext only; panel hashes).
- Do not invent new slot names or payload keys without updating both repos.

---

## 8. Reference implementations

| Side | Location |
| --- | --- |
| Panel routes | `routes/api.php` → `v1/sync` group (`customer.bearer`) |
| Panel user payload | `app/Support/UserSync/UserSyncPayload.php` |
| Panel branding payload | `app/Support/BrandingSync/BrandingSyncPayload.php` |
| Panel outbound users | `app/Services/UserSync/TenantUserPusher.php` |
| Panel outbound branding | `app/Services/BrandingSync/TenantBrandingPusher.php` |
| CMS inbound routes | `routes/api/api-super-admin.php` → `admin-api/v1/sync` |
| CMS outbound client | `app/Services/SuperAdminService.php` (`httpClient()` + Bearer `SECURE_TOKEN`) |

Existing endpoint narrative for older user flows: `docs/USER_SYNC_ENDPOINTS_DOCUMENTATION.md`. Prefer the `/api/v1/sync/*` surface above for new work.
