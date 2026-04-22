# Blackwidow Super Admin Panel — MCP Reference

**Base URL:** `https://superadmin.blackwidow.org.za`  
**Auth:** Laravel Sanctum token (set via `BLACKWIDOW_API_TOKEN` env var)  
**MCP Server:** `blackwidow-site`

---

## Health & Meta

### `site_health`
`GET /api/mcp/health`  
Returns app name and environment. No parameters.

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

## Customer Subscriptions

### `list_customer_subscriptions`
Paginated list. Env blob is omitted from results.

| Param | Type | Notes |
|---|---|---|
| `customer_id` | number | filter |
| `subscription_type_id` | number | filter |
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
