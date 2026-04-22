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

/**
 * @param {string} path - e.g. "/mcp/customers"
 * @param {Record<string, string | number | undefined | null>} [params]
 */
async function apiGetQuery (path, params = {}) {
  const p = path.startsWith('/') ? path : `/${path}`;
  const qs = new URLSearchParams();
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') {
      qs.set(k, String(v));
    }
  }
  const q = qs.toString();
  return apiGet(q ? `${p}?${q}` : p);
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

server.registerTool(
  'list_template_env_variables',
  {
    description:
      'List TemplateEnvVariables rows (per-subscription-type env key templates). Optional filter by subscription_type_id.',
    inputSchema: z.object({
      subscription_type_id: z.number().int().optional().describe('Filter by subscription type id')
    })
  },
  async ({ subscription_type_id: subscriptionTypeId }) => {
    const data = await apiGetQuery('/mcp/template-env-variables', {
      subscription_type_id: subscriptionTypeId
    });
    return {
      content: [{ type: 'text', text: JSON.stringify(data, null, 2) }]
    };
  }
);

server.registerTool(
  'list_env_variables',
  {
    description:
      'List EnvVariables for a customer subscription (key/value rows). Requires customer_subscription_id.',
    inputSchema: z.object({
      customer_subscription_id: z
        .number()
        .int()
        .describe('Customer subscription primary key')
    })
  },
  async ({ customer_subscription_id: customerSubscriptionId }) => {
    const data = await apiGetQuery('/mcp/env-variables', {
      customer_subscription_id: customerSubscriptionId
    });
    return {
      content: [{ type: 'text', text: JSON.stringify(data, null, 2) }]
    };
  }
);

server.registerTool(
  'list_customers',
  {
    description:
      'Paginated customers (sensitive API/S3 fields omitted). Optional: page, per_page (max 100).',
    inputSchema: z.object({
      page: z.number().int().min(1).optional(),
      per_page: z.number().int().min(1).max(100).optional()
    })
  },
  async ({ page, per_page: perPage }) => {
    const data = await apiGetQuery('/mcp/customers', { page, per_page: perPage });
    return {
      content: [{ type: 'text', text: JSON.stringify(data, null, 2) }]
    };
  }
);

server.registerTool(
  'list_customer_subscriptions',
  {
    description:
      'Paginated customer subscriptions with subscriptionType and customer (company). The raw env blob is omitted. Optional: customer_id, subscription_type_id, page, per_page.',
    inputSchema: z.object({
      customer_id: z.number().int().optional(),
      subscription_type_id: z.number().int().optional(),
      page: z.number().int().min(1).optional(),
      per_page: z.number().int().min(1).max(100).optional()
    })
  },
  async ({ customer_id: customerId, subscription_type_id: subscriptionTypeId, page, per_page: perPage }) => {
    const data = await apiGetQuery('/mcp/customer-subscriptions', {
      customer_id: customerId,
      subscription_type_id: subscriptionTypeId,
      page,
      per_page: perPage
    });
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
