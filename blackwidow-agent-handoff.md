# Blackwidow Super Admin Panel — Agent Handoff

## Context

You are working with the **Blackwidow Super Admin Panel**, a Laravel-based web application served at:

**https://superadmin.blackwidow.org.za**

The app is currently running in a **local environment**. It is connected via a custom MCP server named `blackwidow-site`, authenticated with a Laravel Sanctum token.

---

## MCP Setup

The MCP server is configured as follows (from `claude_desktop_config.json`):

```json
{
  "mcpServers": {
    "blackwidow-site": {
      "command": "node",
      "args": [
        "/Users/jacquestredoux/PhpstormProjects/blackwidow_super_admin_panel/mcp/blackwidow-site/index.mjs"
      ],
      "env": {
        "BLACKWIDOW_API_BASE_URL": "https://superadmin.blackwidow.org.za",
        "BLACKWIDOW_API_TOKEN": "4182|eMa6ssdsZCVKljmifv0lloqtoLqHb3T8gv8OxxI172bd889e"
      }
    }
  }
}
```

All MCP tools are **deferred** — you must load them before calling. Load all at once:

```
ToolSearch({ query: "blackwidow-site", max_results: 30 })
```

All tool names are prefixed with `mcp__blackwidow-site__`.

---

## Available MCP Tools

### Health & Meta

| Tool | Method | Description |
|---|---|---|
| `site_health` | GET /api/mcp/health | Returns `{ status, app, environment }` |
| `list_subscription_types` | GET /api/mcp/subscription-types | Lists all subscription types (`id`, `name`, `github_repo`, `project_type`) |

**Confirmed health check response:**
```json
{
  "status": "ok",
  "app": "BlackwidowSuperAdminPanel",
  "environment": "local"
}
```

---

### Customers

| Tool | Description |
|---|---|
| `list_customers` | Paginated list. Params: `page`, `per_page` (max 100). No S3/API secrets returned. |
| `get_customer` | GET one customer. Required: `id` |
| `create_customer` | POST new customer. Required: `company_name`. Cannot set S3 or API token via MCP. |
| `update_customer` | PUT update. Required: `id`. Safe fields only. |
| `delete_customer` | Soft-delete. Required: `id`. |

**Customer fields:**
`company_name`, `max_users`, `docket_description`, `task_description`, `level_one_description`, `level_one_in_use`, `level_two_description`, `level_two_in_use`, `level_three_description`, `level_three_in_use`, `level_four_description`, `level_five_description`

---

### Customer Subscriptions

| Tool | Description |
|---|---|
| `list_customer_subscriptions` | Paginated. Filters: `customer_id`, `subscription_type_id`. Env blob omitted. |
| `get_customer_subscription` | GET one. Required: `id`. Optional: `include_env` (boolean) — returns env as JSON string. |
| `create_customer_subscription` | POST. Required: `url`, `domain`, `database_name`, `subscription_type_id`, `customer_id`. |
| `update_customer_subscription` | PUT. Required: `id`. Optional: `include_env` for response. |
| `delete_customer_subscription` | DELETE by `id`. |

**Subscription fields:**
`url`, `domain`, `database_name`, `subscription_type_id`, `customer_id`, `uuid`, `app_name`, `server_id`, `forge_site_id`, `panic_button_enabled`, `include_env`, `env`, `logo_1`–`logo_5`, `site_created_at`, `ssl_deployed_at`, `github_sent_at`, `env_sent_at`, `deployment_script_sent_at`, `deployed_at`, `deployed_version`

---

### Env Variables (per Subscription)

| Tool | Description |
|---|---|
| `list_env_variables` | Required: `customer_subscription_id` |
| `get_env_variable` | Required: `id` |
| `create_env_variable` | Required: `customer_subscription_id`, `key`. Optional: `value` |
| `update_env_variable` | Required: `id`. Optional: `key`, `value` |
| `delete_env_variable` | Required: `id` |

---

### Template Env Variables (per Subscription Type)

| Tool | Description |
|---|---|
| `list_template_env_variables` | Optional filter: `subscription_type_id` |
| `get_template_env_variable` | Required: `id` |
| `create_template_env_variable` | Required: `subscription_type_id`, `key`. Optional: `value`, `requires_manual_fill`, `admin_label`, `help_text` |
| `update_template_env_variable` | Required: `id`. Same optional fields as create. |
| `delete_template_env_variable` | Required: `id` |

---

## Important Notes

- **S3 credentials and API tokens** cannot be read or written via MCP — admin UI only.
- **Soft-deletes** are used for customers — data is not permanently destroyed.
- **`include_env: true`** on subscription GET or UPDATE returns the full `.env` blob as a JSON string.
- The workspace folder on disk is: `/Users/jacquestredoux/PhpstormProjects/blackwidow_super_admin_panel`
- The project is a **Laravel + Vite** app (node_modules present, `laravel-vite-plugin` detected).
- User: **Jacques** — `jacques@ncloud.africa`

---

## What Has Been Done So Far

1. Confirmed MCP is connected and healthy.
2. Discovered base URL and Sanctum token from `claude_desktop_config.json`.
3. Loaded and documented all 22 MCP tool schemas.
4. Produced this handoff document.

---

## What To Do Next

Awaiting instructions from Jacques. Starting points could include:
- Listing existing customers and subscriptions
- Creating or updating a customer/subscription
- Managing env variables for a deployment
- Exploring the subscription types and template env structure
