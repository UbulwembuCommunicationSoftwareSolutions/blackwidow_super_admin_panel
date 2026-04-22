# Black Widow site MCP (Claude / Cursor)

This is a small [Model Context Protocol](https://modelcontextprotocol.io/) server (stdio) that exposes **tools** which call your Laravel app’s JSON API:

| Tool | API |
|------|-----|
| `site_health` | `GET /api/mcp/health` |
| `list_subscription_types` | `GET /api/mcp/subscription-types` |

Authentication uses **Laravel Sanctum** personal access tokens on the `User` model (`Authorization: Bearer …`).

## 1. Create a Sanctum token (one-time)

With DB configured and migrations run (including `personal_access_tokens`):

```bash
php artisan mcp:create-token
# or: php artisan mcp:create-token you@example.com
```

Copy the printed token. It is shown **once**, then paste it into `BLACKWIDOW_API_TOKEN` in your MCP client config (Claude: `~/Library/Application Support/Claude/claude_desktop_config.json` under `mcpServers.blackwidow-site.env`, or `.cursor/mcp.json` for Cursor).

## 2. Install Node dependencies

```bash
cd mcp/blackwidow-site
npm install
```

## 3. Register the MCP server

Set **env vars** in the client (not in the model chat):

- `BLACKWIDOW_API_BASE_URL` – base URL of the app, e.g. `https://admin.example.com` or `http://127.0.0.1:8000` (no trailing slash required)
- `BLACKWIDOW_API_TOKEN` – the `plainTextToken` from step 1

### Claude Desktop (macOS)

Edit `~/Library/Application Support/Claude/claude_desktop_config.json` and merge a `mcpServers` entry (see `claude_desktop_config.example.json` in this folder). **Fully quit and reopen** Claude.

### Cursor

Copy [`.cursor/mcp.json.example`](../../.cursor/mcp.json.example) into your **Cursor MCP** configuration (or merge the `blackwidow-site` block). Replace `args` with an **absolute path** to `mcp/blackwidow-site/index.mjs` if `${workspaceFolder}` is not supported in your build.

- **Command:** `node`
- **Args:** path to this directory’s `index.mjs`
- **Env:** `BLACKWIDOW_API_BASE_URL`, `BLACKWIDOW_API_TOKEN`

Restart Cursor if tools do not appear. Use a **staging** URL first.

## 4. Security

- Treat the token like a password; rotate if leaked.
- Prefer read-only or narrowly scoped API routes for automation (current MCP routes are read-only).
