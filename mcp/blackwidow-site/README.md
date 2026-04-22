# Black Widow site MCP (Claude / Cursor)

[Model Context Protocol](https://modelcontextprotocol.io/) server (stdio) exposing **tools** that call `GET/POST/PUT/DELETE /api/mcp/*` (Laravel + **Sanctum**).

**Server version:** see `version` in [`index.mjs`](index.mjs) (bump when tools change; restart Claude/Cursor after pulling).

## Tools (summary)

| Area | Tools |
|------|--------|
| Meta | `site_health`, `list_subscription_types` |
| Template env (`TemplateEnvVariables`) | `list_template_env_variables`, `get_template_env_variable`, `create_template_env_variable`, `update_template_env_variable`, `delete_template_env_variable` |
| Site env rows (`EnvVariables`) | `list_env_variables`, `get_env_variable`, `create_env_variable`, `update_env_variable`, `delete_env_variable` |
| Customers | `list_customers`, `get_customer`, `create_customer`, `update_customer`, `delete_customer` |
| Subscriptions | `list_customer_subscriptions`, `get_customer_subscription`, `create_customer_subscription`, `update_customer_subscription`, `delete_customer_subscription` |

**Privacy:** API responses hide `Customer` storage credentials and `token`. `CustomerSubscription` list/show default hides the `env` blob; use `get_customer_subscription` with `include_env: true` or per-key `EnvVariables` tools when you need values.

**Create subscription** requires at minimum: `url`, `domain`, `database_name`, `subscription_type_id`, `customer_id` (see tests and `McpSiteController`).

## 1. Create a Sanctum token (one-time)

```bash
php artisan mcp:create-token
# or: php artisan mcp:create-token you@example.com
```

Copy the token into `BLACKWIDOW_API_TOKEN` in your MCP config.

## 2. Install Node dependencies

```bash
cd mcp/blackwidow-site
npm install
```

## 3. Register the MCP server

Set in the **client env** (not in chat):

- `BLACKWIDOW_API_BASE_URL` – e.g. `https://…` or `http://127.0.0.1:8000`
- `BLACKWIDOW_API_TOKEN` – from step 1

### Claude Desktop (macOS)

From the project root (after `cp claude_desktop_config.example.json claude_desktop_config.json` and editing token/URL if needed):

```bash
mkdir -p ~/Library/Application\ Support/Claude
cp claude_desktop_config.json ~/Library/Application\ Support/Claude/claude_desktop_config.json
```

`claude_desktop_config.json` in the project root is **gitignored** when it contains secrets. Tracked template: [claude_desktop_config.example.json](../../claude_desktop_config.example.json). Merge your `preferences` if Claude resets them.

**Fully quit and reopen** Claude after changes.

### Cursor

Use [`.cursor/mcp.json.example`](../../.cursor/mcp.json.example) — set **absolute** path to `index.mjs` in `args` if needed.

## 4. Security

- The token is **admin-equivalent** for these routes: treat like production credentials; rotate if leaked.
- **CRUD** can change live data: use staging, backups, and least-privilege users where possible.
