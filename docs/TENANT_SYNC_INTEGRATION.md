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

Logos only. Three **wire slot names** (CMS / Firearm / LMS contract). Tenant apps still speak exactly these keys; the panel maps them internally.

| Wire `slot` | Typical use |
| --- | --- |
| `login_logo` | Login screen logo |
| `menu_logo` | Sidebar / menu logo |
| `login_background` | Login background image |

### Panel storage (Super Admin)

Customer defaults live in **Spatie media** on `CustomerBrandingMedia`, with one row per uploaded asset. Named defaults are wired through `customer_brand_slots`:

| Wire `slot` | Customer default (`customer_brand_slots.slot`) |
| --- | --- |
| `login_logo` | `login_logo` |
| `menu_logo` | `menu_logo` |
| `login_background` | `login_background` |

Each **subscription** resolves effective art via `customer_subscription_brand_slots`:

- **Inherit** — no override row, or override points at the same customer-default media → push uses the customer default bytes/URL.
- **Override** — subscription slot references different `CustomerBrandingMedia` → that subscription pushes its override.
- **Cleared** — subscription slot marked cleared → wire payload has `cleared: true` even if a customer default still exists.

Backend Cockpit APIs: `GET/POST …/customers/{id}/branding-media`, `PUT …/brand-slots/{slot}`, subscription-level overrides on the subscription branding endpoints.

**Legacy columns** on `customer_subscriptions` (`logo_1` / `logo_2` / `logo_3` and checksum/timestamp columns) are **deprecated** but still used as a **fallback** when no brand-slot rows exist yet (e.g. before backfill). New work should use customer media + brand slots.

**Outbound tenant types:** only subscriptions whose `subscription_type_id` is listed in `config/branding_sync.php` → `tenant_subscription_types` (currently **`[1, 2]`** — CMS console and Firearm) receive per-subscription pushes. LMS uses the hub path in §6.

**Backfill from legacy files:**

```bash
php artisan branding:backfill-customer-media
# --dry-run to preview
```

Creates `CustomerBrandingMedia` + customer/subscription brand slots from existing `logo_1`/`logo_2`/`logo_3` paths.

### Wire contract (unchanged for CMS / Firearm)

Tenants must still implement the same JSON shape and endpoints below. Super Admin builds payloads from effective media (or legacy fallback); tenants do not need to know about Spatie or brand-slot tables.

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

## 6. Customer sync (shared LMS hub)

The Academy / LMS app is **multi-tenant**: one deployment serves every customer. User and branding sync still use per-tenant `customer.bearer` auth (`app_url` + that customer’s `customers.token`). Customer directory sync uses a dedicated middleware because the LMS pulls **all** customers in one call.

### Super Admin configuration

| Setting | Purpose |
| --- | --- |
| `LMS_SYNC_TOKEN` | Shared secret; must match the LMS `SECURE_TOKEN`. Used for LMS → panel `GET` and panel → LMS `POST`. |
| `customer_subscriptions` with `subscription_type_id` **12** (`lms`) | Registers the LMS hub URL on `app_url` so auth knows which host is allowed. |

Add subscription type **12** / slug `lms` in `SubscriptionType::URL_SLUGS` when provisioning Forge hostnames (`{customer}.lms.{vertical}`).

### LMS → Super Admin (pull / reconcile)

```
GET {SUPERADMIN_API}/api/v1/sync/customers?app_url={LMS_APP_URL}
Authorization: Bearer {LMS_SYNC_TOKEN}
```

Alternatively, Bearer may be `customers.token` for **any** customer that has an LMS subscription whose `url` matches `app_url` (useful before the shared token is configured).

Response:

```json
{
  "success": true,
  "data": [
    {
      "super_admin_customer_id": 1,
      "uuid": "…",
      "company_name": "Acme",
      "slug": "acme",
      "max_users": 25,
      "is_active": true,
      "sync_hash": "sha256…"
    }
  ]
}
```

All non–soft-deleted customers are returned (not scoped to the caller’s customer id).

### Super Admin → LMS (push on change)

When a `Customer` is created or updated, `PushCustomerToTenantsJob` POSTs to each of that customer’s LMS subscriptions:

```
POST {lms_url}/admin-api/v1/sync/customers
Authorization: Bearer {LMS_SYNC_TOKEN or customers.token}
```

Wire shape matches the LMS `CustomerSyncPayload` (same fields as each row in `data` above).

### User sync (LMS hub)

LMS admin users (`App\Models\User`, not Members) use the same canonical user contract as the CMS, with two LMS-specific wire fields on the payload:

- `super_admin_customer_id` — panel `customers.id` so the hub can set `users.customer_id`
- `customer_slug` — fallback lookup on the LMS `customers` table
- `lms_access` — boolean on `customer_users` (subscription type **12**)

**Panel → LMS:** `TenantUserPusher` includes subscription type **12** in `config/user_sync.php`. Outbound calls use `Authorization: Bearer {LMS_SYNC_TOKEN}` (falls back to `customers.token`). Users are pushed to the LMS subscription URL only when `lms_access` is true or `is_system_admin` is true.

**LMS inbound:** `POST {LMS_APP_URL}/admin-api/v1/sync/users` (+ archive / restore / password), Bearer = LMS `SECURE_TOKEN`.

**LMS outbound:** `POST {SUPERADMIN_API}/api/v1/sync/users` with `origin: lms` when the local user has `super_admin_user_id`.

### Branding sync (LMS hub)

Customer-default branding changes on the panel can also POST to the **shared LMS deployment** (when `branding_sync.lms_hub_enabled` is true). This is **not** the same auth model as CMS/Firearm per-tenant pushes:

```
POST {LMS_APP_URL}/admin-api/v1/sync/branding
Authorization: Bearer {LMS_SYNC_TOKEN}
Content-Type: application/json
```

Body (note `super_admin_customer_id` **inside** `branding`, not `app_url` + per-customer `customers.token`):

```json
{
  "app_url": "https://lms-hub.example.test",
  "origin": "super_admin",
  "branding": {
    "super_admin_customer_id": 1,
    "slot": "login_logo",
    "url": "https://superadmin.example/storage/…",
    "checksum": "sha256:…",
    "cleared": false,
    "updated_at": "2026-09-22T12:00:00+00:00"
  }
}
```

The LMS maps `super_admin_customer_id` → local `customers.super_admin_customer_id`, then upserts **`branding_settings`** for that customer (`logo_path` / `menu_logo_path` / `login_background_path` plus checksum + timestamp columns). Same three wire slots as §5; LWW + checksum rules apply.

**LMS → panel (outbound):** when an LMS admin uploads customer-scoped login, menu, or login-background assets, the hub queues `PushBrandingToSuperAdminJob` → `POST {SUPERADMIN_API}/api/v1/sync/branding` with `app_url` = LMS subscription URL and the usual canonical `branding` object (no `super_admin_customer_id` on that direction).

User sync on the LMS still uses per-tenant bearer where applicable; **branding push to the LMS hub always uses `LMS_SYNC_TOKEN`** (falls back to `customers.token` only if the token env is empty).

---

## 7. Minimal “new app” build order

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

## 8. What not to do

- Do not call legacy logo or user bulk endpoints for routine sync (`sync-logos`, ad-hoc `Mail::`, etc.).
- Do not match users only by email when both IDs exist.
- Do not push after applying an inbound write (echo loops).
- Do not send password hashes from tenant → panel on upsert (cleartext only; panel hashes).
- Do not invent new slot names or payload keys without updating both repos.

---

## 9. Reference implementations

| Side | Location |
| --- | --- |
| Panel routes | `routes/api.php` → `v1/sync` group (`customer.bearer`) |
| Panel user payload | `app/Support/UserSync/UserSyncPayload.php` |
| Panel branding payload | `app/Support/BrandingSync/BrandingSyncPayload.php` |
| Panel outbound users | `app/Services/UserSync/TenantUserPusher.php` |
| Panel outbound branding | `app/Services/BrandingSync/TenantBrandingPusher.php` |
| Panel customer list (LMS) | `app/Http/Controllers/Api/V1/CustomerSyncController.php` (`lms.bearer`) |
| Panel outbound customers | `app/Services/CustomerSync/TenantCustomerPusher.php` |
| CMS inbound routes | `routes/api/api-super-admin.php` → `admin-api/v1/sync` |
| CMS outbound client | `app/Services/SuperAdminService.php` (`httpClient()` + Bearer `SECURE_TOKEN`) |

Existing endpoint narrative for older user flows: `docs/USER_SYNC_ENDPOINTS_DOCUMENTATION.md`. Prefer the `/api/v1/sync/*` surface above for new work.
