# Backend API (Filament replacement)

Sanctum-authenticated JSON API for a future custom admin frontend. It mirrors Filament resource functions.

- Base URL: `/api/backend`
- Auth user: admin `User` (same model as Filament)
- MCP (`/api/mcp`) and CRM (`/api/crm`) are unchanged

## Conventions

| Concern | Contract |
| --- | --- |
| Single record | `{ "data": { ... } }` |
| Lists | Laravel paginator (`data`, `current_page`, `per_page`, …). `per_page` default **25**, max **100** |
| Deletes | `{ "ok": true, "id": N }` |
| Auth errors | **401** unauthenticated, **403** failed Shield policy |
| Missing records | **404** |
| Validation | **422** |
| Upstream Forge failures | **502** with `{ "message": "..." }` |

Soft-deleted resources also support:

- `POST /{resource}/{id}/restore`
- `DELETE /{resource}/{id}/force`
- list filter `trashed=with|only`

## List search and sorting

Every list endpoint accepts these in addition to `page` and `per_page`:

| Parameter | Contract |
| --- | --- |
| `search` | Case-insensitive partial match across that resource's searchable columns (OR'd together). Omit or send blank to skip |
| `sort` | A single column from that resource's sort allowlist. An unlisted column is a **422** on `sort` |
| `direction` | `asc` (default) or `desc`. Anything else is a **422** on `direction` |

Omitting `sort` preserves each resource's own default order: `id` everywhere except `/env-variables` (`key`) and `/template-env-variables` (`subscription_type_id`, then `key`). When sorting by a non-unique column, `id` is appended as a tiebreaker so paging stays stable.

Searchable and sortable columns per resource:

| Resource | `search` matches | `sort` allows |
| --- | --- | --- |
| `/customers` | `company_name`, `docket_description`, `task_description`, `level_one_description`…`level_five_description`, `uuid` | `id`, `company_name`, `max_users`, `created_at`, `updated_at`, `deleted_at` |
| `/customer-subscriptions` | `url`, `domain`, `app_name`, `database_name`, `database_user`, `forge_site_id`, `deployed_version`, related type `name`, related customer `company_name` | `id`, `url`, `domain`, `app_name`, `database_name`, `server_id`, `subscription_type_id`, `customer_id`, `deployed_version`, the six pipeline timestamps, `created_at`, `updated_at` |
| `/customer-subscriptions/{id}/deployment-jobs` | `batch_id`, `job_name`, `status`, `error_message` | `id`, `batch_id`, `position`, `job_name`, `status`, `started_at`, `finished_at`, `created_at` |
| `/customer-users` | `email_address`, `first_name`, `last_name`, `cellphone` | `id`, `email_address`, `first_name`, `last_name`, `cellphone`, `customer_id`, `is_system_admin`, `created_at`, `updated_at`, `deleted_at` |
| `/users` | `name`, `email` | `id`, `name`, `email`, `email_verified_at`, `created_at`, `updated_at` |
| `/user-customers` | related user `name` / `email`, related customer `company_name` | `id`, `user_id`, `customer_id`, `created_at`, `updated_at` |
| `/subscription-types` | `name`, `github_repo`, `branch`, `project_type`, `master_version` | same five plus `id`, `created_at`, `updated_at` |
| `/deployment-scripts` | `script`, related subscription `url` | `id`, `customer_subscription_id`, `created_at`, `updated_at` |
| `/deployment-templates` | `script`, related type `name` | `id`, `subscription_type_id`, `created_at`, `updated_at` |
| `/env-variables` | `key`, `value`, related subscription `url` | `id`, `key`, `customer_subscription_id`, `created_at`, `updated_at` |
| `/template-env-variables` | `key`, `value`, `admin_label`, `help_text`, related type `name` | `id`, `key`, `value`, `admin_label`, `requires_manual_fill`, `subscription_type_id`, `created_at`, `updated_at` |
| `/forge-servers` | `name`, `ip_address`, and `forge_server_id` when the term is numeric | `id`, `forge_server_id`, `name`, `ip_address`, `created_at`, `updated_at` |
| `/nginx-templates` | `name`, `server_id` | `id`, `name`, `server_id`, `template_id`, `created_at`, `updated_at` |

The customer API `token` is deliberately not searchable — it is a secret.

`deployment_scripts.script` and `deployment_templates.script` are `longText` with no fulltext index, so searching them is a full table scan. Fine at current volumes; revisit if these grow.

Hidden on read: customer secrets (`token`, `google_api_key`, S3 fields), subscription `env` / `database_password`, user and customer-user passwords. Pass `include_env=1` on customer-subscription show/store/update to include `env`. Customer secrets are available only on `GET /customers/{id}/credentials`.

Authorize with existing Filament Shield policies (`ViewAny:Customer`, `Update:CustomerSubscription`, …). Reuse the same roles/permissions as Filament.

## Auth

Public:

- `POST /api/backend/login` — `{ email, password }` → `{ data: { token, user } }`
  - Token is a Sanctum personal access token with ability `backend`
  - `user` includes `roles` (names) and `permissions` (names)

Authenticated (`Authorization: Bearer {token}`):

- `POST /api/backend/logout` — revoke the current token → `{ ok: true }`
- `GET /api/backend/user` — current user + roles + permission names

## Resources

All paths below are prefixed with `/api/backend`.

### Customers (`Customer`, soft deletes)

- `GET/POST /customers`
- `GET/PUT/DELETE /customers/{id}`
- `POST /customers/{id}/restore`
- `DELETE /customers/{id}/force`
- `GET /customers/{id}/credentials` — same View permission as show
- `POST /customers/{id}/sync-env` — same Update permission as update

Create/update fields: `company_name` (required on create), S3 fields, mail fields, `token`, `google_api_key`, descriptions, level toggles, `max_users`. Writes stay on `POST/PUT /customers/{id}`; omit a secret key when the field was never revealed so a save does not blank it.

List, show, store, update, and restore **never** return `token`, `google_api_key`, `mail_password`, or any `s3_*` field. They do include:

| Field | Meaning |
| --- | --- |
| `google_api_key_set` | `true` when a Google API key is stored |
| `s3_configured` | `true` only when **all four** of `s3_endpoint`, `s3_key`, `s3_secret`, and `s3_bucket` are filled |
| `s3_partial` | `true` when some but not all of those four are filled |
| `mail_configured` | `true` when `mail_mailer`, `mail_host`, and `mail_from_address` are all filled |
| `mail_password_set` | `true` when an SMTP password is stored |
| `customer_subscriptions_count` | `withCount` of sites |
| `customer_users_count` | `withCount` of client users |

`GET /customers/{id}/credentials` returns `{ "data": { token, google_api_key, s3_endpoint, s3_key, s3_secret, s3_region, s3_bucket, s3_use_path_style_endpoint, mail_mailer, mail_transport, mail_host, mail_url, mail_port, mail_username, mail_password, mail_encryption, mail_scheme, mail_from_address, mail_from_name, mail_ehlo_domain } }` for the Reveal UI.

#### Per-customer env configuration

The Google API key and the mail fields are mirrored into the `.env` of **every** subscription the customer owns:

| Customer field | Subscription `.env` key | Rules |
| --- | --- | --- |
| `google_api_key` | `GOOGLE_MAPS_API_KEY` | |
| `mail_mailer` | `MAIL_MAILER` | one of `smtp`, `sendmail`, `ses`, `mailgun`, `postmark`, `resend`, `log`, `array`, `failover`, `roundrobin` |
| `mail_transport` | `MAIL_TRANSPORT` | same list; falls back to `mail_mailer` |
| `mail_host` | `MAIL_HOST` | |
| `mail_url` | `MAIL_URL` | falls back to `mail_host` |
| `mail_port` | `MAIL_PORT` | integer 1–65535 |
| `mail_username` | `MAIL_USERNAME` | |
| `mail_password` | `MAIL_PASSWORD` | hidden on read |
| `mail_encryption` | `MAIL_ENCRYPTION` | |
| `mail_scheme` | `MAIL_SCHEME` | |
| `mail_from_address` | `MAIL_FROM_ADDRESS` | must be a valid email |
| `mail_from_name` | `MAIL_FROM_NAME` | |
| `mail_ehlo_domain` | `MAIL_EHLO_DOMAIN` | |

Every field is nullable — send `null` to unset it. A blank field is skipped, so the subscription keeps whatever its subscription-type env template ships with. Only keys a subscription already has are written, so a static site whose template has no `MAIL_*` never gains them.

Saving any of these fields queues `SyncCustomerEnvToSubscriptionsJob`, which rewrites the `env_variables` rows and pushes the changed envs to Forge. Subscriptions without a Forge site are updated in the database and skipped for the push. `POST /customers/{id}/sync-env` replays the same job on demand and returns `{ ok, customer_subscriptions_count, keys }`, or `422` when the customer has nothing configured. A site with cached config needs a redeploy before the new values take effect.

Nested reads: `GET /customer-subscriptions?customer_id=`, `GET /customer-users?customer_id=`.

### Customer subscriptions

- `GET/POST /customer-subscriptions`
- `GET/PUT/DELETE /customer-subscriptions/{id}`
- Filters: `customer_id`, `subscription_type_id`

Create accepts Filament/MCP deployment flags: `trigger_site_deployment`, `force_site_deployment`.

Custom actions (same services/jobs as Filament):

- `POST /customer-subscriptions/{id}/recreate-site` — `SiteDeploymentScheduler::scheduleSiteCreationOnly()` when `forge_site_id` is empty and `server_id` is set
- `POST /customer-subscriptions/{id}/generate-logos` — `CustomerSubscriptionService::generatePWALogos()`
- `POST /customer-subscriptions/{id}/deploy` — dispatch `DeploySite`
- `POST /customer-subscriptions/{id}/pull-env` — `ForgeService::getSiteEnvironment()` (requires `server_id` + `forge_site_id`)
- `PUT /customer-subscriptions/{id}/server` — `{ "server_id": <forge_server_id> }`
- `GET /customer-subscriptions/{id}/pipeline-steps`
- `POST /customer-subscriptions/{id}/pipeline-steps/{index}`
- `GET /customer-subscriptions/{id}/deployment-jobs`
- `POST /customer-subscriptions/{id}/deployment-jobs/{jobId}/retry` — re-dispatch that row in place so later steps in the same batch resume on success
- `POST /customer-subscriptions/{id}/deployment-jobs/{jobId}/run-alone` — copy that row into a new single-step batch (original batch is left untouched)

Env row edits live on `/env-variables`.

#### Logos

`logo_1`–`logo_5` hold paths on the `public` disk. Every subscription payload
also carries a read-only `logo_urls` map of the filled slots, so a client on
another host does not have to know the disk layout:

```json
{ "logo_urls": { "logo_1": "https://superadmin.example/storage/abc.png" } }
```

Files are uploaded through a dedicated multipart endpoint, since the JSON
`PUT /customer-subscriptions/{id}` only sets paths:

- `POST /customer-subscriptions/{id}/logos` — multipart, gated on `Update:CustomerSubscription`

Send any subset of `logo_1`–`logo_5` as files, and/or `clear[]` with the slot
names to empty. Files accept `jpg,jpeg,png,gif,webp,svg` up to 10 MB, matching
the Filament form. Replacing or clearing a slot deletes the previous file once
the new one is safely stored. Uploading and clearing the same slot in one
request is a 422, as is a request that does neither. Responds with the updated
subscription.

`GET /subscription-types` (and `show`) expose `logo_descriptions`: a five-entry
list naming what each slot means for that product, with unused slots as `null`
so clients can label the upload fields without duplicating the mapping.

### Customer users (`CustomerUser`, soft deletes)

- `GET/POST /customer-users`
- `GET/PUT/DELETE /customer-users/{id}`
- `POST /customer-users/{id}/restore`
- `DELETE /customer-users/{id}/force`
- Filter: `customer_id`

Actions:

- `POST /customer-users/{id}/update-password` — `{ new_password, confirm_password }` (min 6, must match). Hashed via the model mutator.
- `POST /customer-users/{id}/send-welcome-email` — `SendWelcomeEmailJob`
- `POST /customer-users/{id}/send-login-email` — `{ subscription_type_id }` → `SendSubscriptionEmailJob` (422 if no subscription or no access)
- `PUT /customer-users/{id}/access-rights` — access toggles + `is_system_admin`

### Admin users

- `GET/POST /users`
- `GET/PUT/DELETE /users/{id}`

Fields: `name`, `email`, `email_verified_at`, `password` (required on create, optional on update), `roles` (array of role names or IDs; synced with Spatie).

`email` is unique, ignoring the record being updated.

Password hashes are never returned.

### Roles

- `GET /roles` — `{ "data": [{ "id": 1, "name": "super_admin" }] }`, ordered by name

Unpaginated role picker for the admin user form. Roles are still assigned
through the `roles` field on `POST`/`PUT /users`; this endpoint is read-only.

Gated on **`ViewAny:User`**, with `ViewAny:Role` also honoured if granted.
Shield generates permissions for the twelve resource models only, so no
environment actually has a `*:Role` permission — a `super_admin` returns false
for `ViewAny:Role` — and gating solely on it left the picker dead everywhere.
Note also that Shield's `super_admin` gate bypass is registered by the Filament
panel, so it does not apply to `/api/backend` requests; API authorisation always
comes down to explicitly granted permissions.

This exposes nothing new, since `GET /users` already returns each user's roles
by name.

### Global search

- `GET /search?q={term}` — grouped matches for the topbar search palette

```json
{
  "data": [
    {
      "type": "customer",
      "label": "Customers",
      "has_more": false,
      "results": [{ "id": 1, "title": "Acme Holdings", "subtitle": null }]
    }
  ]
}
```

`q` is required and must be at least 2 characters; anything shorter is a 422.
Groups are returned in a fixed order and each is capped at 5 results, with
`has_more` set when more exist. A group with no matches is omitted entirely.

Each group is gated on its own `ViewAny` policy and **skipped rather than
refused** when the operator lacks it, so a restricted account gets a smaller
result set instead of a 403 for the whole search.

| `type` | Model | Matched on |
| --- | --- | --- |
| `customer` | `Customer` | `company_name`, `uuid` |
| `subscription` | `CustomerSubscription` | `url`, `domain`, `app_name`, `customer.company_name` |
| `subscription-type` | `SubscriptionType` | `name`, `github_repo` |
| `template-env-variable` | `TemplateEnvVariables` | `key`, `subscriptionType.name` |
| `nginx-template` | `NginxTemplate` | `name` |
| `deployment-template` | `DeploymentTemplate` | `subscriptionType.name` |

Match semantics are identical to the per-list `search` parameter, so global and
in-list search agree on what a term hits. `title` is always populated — the
Filament resources for nginx and deployment templates set no record title and
render the model label instead, which this endpoint deliberately does not copy.

### User ↔ customer pivots (`UserCustomer`, soft deletes)

- `GET/POST /user-customers`
- `GET/PUT/DELETE /user-customers/{id}`
- `POST /user-customers/{id}/restore`
- `DELETE /user-customers/{id}/force`
- Filters: `user_id`, `customer_id`

### Deployment scripts

- `GET/POST /deployment-scripts`
- `GET/PUT/DELETE /deployment-scripts/{id}`
- Filter: `customer_subscription_id`
- Fields: `script`, `customer_subscription_id`

### Deployment templates

- `GET/POST /deployment-templates`
- `GET/PUT/DELETE /deployment-templates/{id}`
- Filter: `subscription_type_id`
- Fields: `script`, `subscription_type_id`

### Env variables

- `GET/POST /env-variables`
- `GET/PUT/DELETE /env-variables/{id}`
- Filter: `customer_subscription_id`
- Fields: `key`, `value`, `customer_subscription_id`

### Template env variables (`TemplateEnvVariables`)

- `GET/POST /template-env-variables`
- `GET/PUT/DELETE /template-env-variables/{id}`
- Filter: `subscription_type_id`
- Fields: `key`, `value`, `requires_manual_fill`, `admin_label`, `help_text`, `subscription_type_id`

`GET /template-env-variables` adds a `summary` object alongside the usual
paginator keys:

```json
{ "data": [], "total": 7, "summary": { "total": 7, "manual": 3 } }
```

`summary.total` and `summary.manual` count the whole filtered set — both the
`subscription_type_id` filter and `search` apply — so the counter stays correct
past page one. `manual` counts rows with `requires_manual_fill` set.

### Forge servers

- `GET/POST /forge-servers`
- `GET/PUT/DELETE /forge-servers/{id}`
- `POST /forge-servers/sync` — `ForgeServerSyncService::syncFromApi()`
- Fields: `forge_server_id`, `name`, `ip_address`

`forge_server_id` is unique, ignoring the record being updated, because the Forge
sync matches rows on it. There is no database unique index yet, only the validation rule.

### Nginx templates

- `GET/POST /nginx-templates`
- `GET/PUT/DELETE /nginx-templates/{id}`
- Filter: `server_id`
- Fields: `name`, `server_id`, `template_id`

### Subscription types (soft deletes)

- `GET/POST /subscription-types`
- `GET/PUT/DELETE /subscription-types/{id}`
- `POST /subscription-types/{id}/restore`
- `DELETE /subscription-types/{id}/force`
- Fields: `name`, `github_repo`, `branch`, `project_type`, `master_version`, `public_dir`

## Not exposed

- Filament-only navigation (`backToCustomer`, `navigate`, `backToEdit`, `verifyUrl`)
- Shield `Role` write operations — `GET /roles` is read-only; assign roles via `POST/PUT /users` `roles`
- Unused exporters
- MCP and CRM endpoints

## Token auth vs SPA cookies

This API uses bearer tokens first. Stateful Sanctum SPA cookie/CSRF can be added later if the frontend is first-party on a configured domain.
