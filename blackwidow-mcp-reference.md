# Blackwidow Super Admin Panel — MCP Reference

**Base URL:** `https://superadmin.blackwidow.org.za`  
**Auth:** Laravel Sanctum token (set via `BLACKWIDOW_API_TOKEN` env var)  
**MCP Server:** `blackwidow-site` (v1.2.0)

---

## Health & Meta

### `site_health`
`GET /api/mcp/health`  
Returns app name and environment. No parameters.

### `site_overview`
`GET /api/mcp/overview`  
Aggregate totals: customers (active + trashed), subscriptions (deployed / not), by subscription type, customers at or over `max_users`, deployment jobs by status. No parameters.

### `list_subscription_types`
`GET /api/mcp/subscription-types`  
Returns all subscription types (`id`, `name`, `github_repo`, `project_type`). No parameters.

---

## Customers

### `list_customers`
Paginated list of customers. S3 and API secrets are excluded.

| Param | Type | Notes |
|---|---|---|
| `page` | number | min 1 |
| `per_page` | number | min 1, max 100 |
| `search` | string | partial match on `company_name` |
| `sort` | string | `id`, `company_name`, `created_at` |
| `direction` | string | `asc` / `desc` |
| `with_counts` | boolean | adds `customer_subscriptions_count`, `customer_users_count` |
| `trashed` | string | `with` or `only` (soft-deletes) |

### `get_customer`
GET one customer by ID.

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |

### `create_customer`
POST create a new customer. Cannot set S3 or API token via MCP.

| Param | Type | Required |
|---|---|---|
| `company_name` | string | ✅ |
| `max_users` | number | |
| `docket_description` | string | |
| `task_description` | string | |
| `level_one_description` | string | |
| `level_one_in_use` | boolean | |
| `level_two_description` | string | |
| `level_two_in_use` | boolean | |
| `level_three_description` | string | |
| `level_three_in_use` | boolean | |
| `level_four_description` | string | |
| `level_five_description` | string | |

### `update_customer`
PUT update a customer (safe fields only).

Same fields as `create_customer` plus `id` (required).

### `delete_customer`
DELETE (soft-delete) a customer by ID.

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |

---

## Customer Users (read-only)

### `list_customer_users`
Paginated list. `password`, `sync_hash`, and `remember_token` are always hidden.

| Param | Type | Notes |
|---|---|---|
| `customer_id` | number | filter |
| `search` | string | first/last name or email |
| `trashed` | string | `with` / `only` |
| `console_access` … `stock_access` | boolean | access flag filters |
| `page` / `per_page` | number | pagination |

### `get_customer_user`
GET one customer user by ID (secrets hidden).

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |

---

## Customer Subscriptions

### `list_customer_subscriptions`
Paginated list. Env blob is omitted from results.

| Param | Type | Notes |
|---|---|---|
| `customer_id` | number | filter |
| `subscription_type_id` | number | filter |
| `search` | string | url / domain / app_name |
| `deployed` | boolean | filter on `deployed_at` null state |
| `sort` | string | `id`, `url`, `domain`, `app_name`, `created_at`, `deployed_at` |
| `direction` | string | `asc` / `desc` |
| `page` | number | min 1 |
| `per_page` | number | min 1, max 100 |

### `get_customer_subscription`
GET one subscription by ID.

| Param | Type | Required | Notes |
|---|---|---|---|
| `id` | number | ✅ | |
| `include_env` | boolean | | Returns env as JSON string if true |

### `create_customer_subscription`
POST create a subscription.

| Param | Type | Required |
|---|---|---|
| `url` | string | ✅ |
| `domain` | string | ✅ |
| `database_name` | string | ✅ |
| `subscription_type_id` | number | ✅ |
| `customer_id` | number | ✅ |
| `uuid` | string | |
| `app_name` | any | |
| `server_id` | any | |
| `forge_site_id` | any | |
| `panic_button_enabled` | boolean | |
| `include_env` | boolean | |
| `env` | any | |
| `logo_1–5` | string | |
| `site_created_at` | string | |
| `ssl_deployed_at` | string | |
| `github_sent_at` | string | |
| `env_sent_at` | string | |
| `deployment_script_sent_at` | string | |
| `deployed_at` | string | |
| `deployed_version` | any | |

### `update_customer_subscription`
PUT update a subscription by ID. Same fields as create plus `id` (required).

### `delete_customer_subscription`
DELETE subscription by ID.

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |

### `compare_subscription_env`
`GET /api/mcp/customer-subscriptions/{id}/env-diff`  
Database-only drift check: template keys vs `EnvVariables` rows vs last-pushed `env` blob. Does **not** call Forge.

| Param | Type | Required | Notes |
|---|---|---|---|
| `id` | number | ✅ | subscription id |
| `include_values` | boolean | | Include configured/blob secret values in mismatches |

Returns `missing_keys`, `extra_keys`, `value_mismatches`, and key counts.

---

## Deployment Jobs (read-only)

### `list_deployment_jobs`
Paginated list. `forge_log` omitted unless `include_log` is true.

| Param | Type | Notes |
|---|---|---|
| `customer_subscription_id` | number | filter |
| `status` | string | `pending`, `running`, `completed`, `failed` |
| `batch_id` | string | filter |
| `include_log` | boolean | include `forge_log` |
| `page` / `per_page` | number | pagination |

### `get_deployment_job`
GET one deployment job by ID.

| Param | Type | Required | Notes |
|---|---|---|---|
| `id` | number | ✅ | |
| `include_log` | boolean | | include `forge_log` |

---

## Env Variables (per Subscription)

### `list_env_variables`
List all env key/value rows for a subscription.

| Param | Type | Required |
|---|---|---|
| `customer_subscription_id` | number | ✅ |

### `get_env_variable`
GET one env row by ID.

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |

### `create_env_variable`
POST create an env row for a subscription.

| Param | Type | Required |
|---|---|---|
| `customer_subscription_id` | number | ✅ |
| `key` | string | ✅ |
| `value` | any | |

### `update_env_variable`
PUT update an env row by ID.

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |
| `key` | string | |
| `value` | any | |

### `delete_env_variable`
DELETE an env row by ID.

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |

---

## Template Env Variables (per Subscription Type)

### `list_template_env_variables`
List template env rows, optionally filtered by subscription type.

| Param | Type | Notes |
|---|---|---|
| `subscription_type_id` | number | optional filter |

### `get_template_env_variable`
GET one template env row by ID.

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |

### `create_template_env_variable`
POST create a template env row.

| Param | Type | Required |
|---|---|---|
| `subscription_type_id` | number | ✅ |
| `key` | string | ✅ |
| `value` | any | |
| `requires_manual_fill` | boolean | |
| `admin_label` | any | |
| `help_text` | any | |

### `update_template_env_variable`
PUT update a template env row by ID.

Same fields as create plus `id` (required).

### `delete_template_env_variable`
DELETE a template env row by ID.

| Param | Type | Required |
|---|---|---|
| `id` | number | ✅ |

---

## Notes for Agents

- All MCP tool names are prefixed with `mcp__blackwidow-site__`
- Tools must be loaded via `ToolSearch` before calling (they are deferred)
- Load all at once: `ToolSearch({ query: "blackwidow-site", max_results: 30 })`
- S3 credentials and API tokens cannot be set or read via MCP — admin UI only
- Soft-deletes are used for customers (data is not permanently destroyed)
- `include_env: true` on subscription GET/UPDATE returns the full env blob as a JSON string
- `compare_subscription_env` is database-only; live Forge `.env` comparison is a future phase
