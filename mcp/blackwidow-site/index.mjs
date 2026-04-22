#!/usr/bin/env node
/**
 * Local MCP server: exposes tools that call the Laravel app JSON API (Sanctum bearer token).
 * Configure BLACKWIDOW_API_BASE_URL and BLACKWIDOW_API_TOKEN in the MCP client (env), not in chat.
 */
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import * as z from 'zod/v4';

function requireEnv (name) {
  const v = process.env[name];
  if (!v) {
    throw new Error(
      `Missing environment variable ${name}. Set it in your MCP server config (Claude Desktop or Cursor).`
    );
  }
  return v;
}

function apiBaseUrl () {
  const raw = requireEnv('BLACKWIDOW_API_BASE_URL');
  return raw.replace(/\/$/, '');
}

function bearer () {
  return requireEnv('BLACKWIDOW_API_TOKEN');
}

async function apiGet (path) {
  const base = apiBaseUrl();
  const p = path.startsWith('/') ? path : `/${path}`;
  const url = `${base}/api${p}`;
  const res = await fetch(url, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${bearer()}`
    }
  });
  const text = await res.text();
  let body;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = text;
  }
  if (!res.ok) {
    const err = new Error(`HTTP ${res.status} ${res.statusText}: ${typeof body === 'string' ? body : JSON.stringify(body)}`);
    err.status = res.status;
    throw err;
  }
  return body;
}

const server = new McpServer({
  name: 'blackwidow-site',
  version: '1.0.0'
});

server.registerTool(
  'site_health',
  {
    description:
      'Call GET /api/mcp/health on the Black Widow admin app (requires Sanctum token). Returns app name and environment.',
    inputSchema: z.object({})
  },
  async () => {
    const data = await apiGet('/mcp/health');
    return {
      content: [{ type: 'text', text: JSON.stringify(data, null, 2) }]
    };
  }
);

server.registerTool(
  'list_subscription_types',
  {
    description:
      'List subscription types (id, name, github_repo, project_type) from GET /api/mcp/subscription-types.',
    inputSchema: z.object({})
  },
  async () => {
    const data = await apiGet('/mcp/subscription-types');
    return {
      content: [{ type: 'text', text: JSON.stringify(data, null, 2) }]
    };
  }
);

async function main () {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
