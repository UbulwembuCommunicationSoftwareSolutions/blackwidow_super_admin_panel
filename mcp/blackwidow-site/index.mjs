#!/usr/bin/env node
/**
 * MCP server: tools call Laravel /api/mcp/* (Sanctum). Supports full CRUD on template env, env rows, customers, subscriptions.
 */
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import * as z from 'zod/v4';

function requireEnv (name) {
  const v = process.env[name];
  if (!v) {
    throw new Error(
      `Missing environment variable ${name}. Set it in your MCP server config.`
    );
  }
  return v;
}

function apiBaseUrl () {
  return requireEnv('BLACKWIDOW_API_BASE_URL').replace(/\/$/, '');
}

function bearer () {
  return requireEnv('BLACKWIDOW_API_TOKEN');
}

/**
 * @param {string} method
 * @param {string} path
 * @param {Record<string, string | number | boolean | undefined | null>} [query]
 * @param {Record<string, unknown> | null} [jsonBody] — set for POST/PUT; omit for GET/DELETE
 */
async function apiRequest (method, path, query, jsonBody) {
  const p = path.startsWith('/') ? path : `/${path}`;
  let full = p;
  if (query && Object.keys(query).length) {
    const qs = new URLSearchParams();
    for (const [k, v] of Object.entries(query)) {
      if (v !== undefined && v !== null && v !== '') {
        qs.set(k, String(v));
      }
    }
    const q = qs.toString();
    if (q) full = `${p}?${q}`;
  }
  const url = `${apiBaseUrl()}/api${full}`;
  const res = await fetch(url, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `Bearer ${bearer()}`
    },
    body:
      jsonBody !== null && jsonBody !== undefined
        ? JSON.stringify(jsonBody)
        : undefined
  });
  const text = await res.text();
  let body;
  try {
    body = text ? JSON.parse(text) : null;
  } catch {
    body = text;
  }
  if (!res.ok) {
    const err = new Error(
      `HTTP ${res.status} ${res.statusText}: ${typeof body === 'string' ? body : JSON.stringify(body)}`
    );
    err.status = res.status;
    throw err;
  }
  return body;
}

const get = (path, q) => apiRequest('GET', path, q, undefined);
const post = (path, body) => apiRequest('POST', path, null, body);
const put = (path, body) => apiRequest('PUT', path, null, body);
const del = (path) => apiRequest('DELETE', path, null, null);

const server = new McpServer({
  name: 'blackwidow-site',
  version: '1.1.0'
});

const textResult = (data) => ({
  content: [{ type: 'text', text: JSON.stringify(data, null, 2) }]
});

// --- Health & subscription types (unchanged) ---

server.registerTool(
  'site_health',
  { description: 'GET /api/mcp/health', inputSchema: z.object({}) },
  async () => textResult(await get('/mcp/health'))
);

server.registerTool(
  'list_subscription_types',
  { description: 'GET /api/mcp/subscription-types', inputSchema: z.object({}) },
  async () => textResult(await get('/mcp/subscription-types'))
);

// --- Template env variables (TemplateEnvVariables model) ---

server.registerTool(
  'list_template_env_variables',
  {
    description: 'List template env rows. Query: subscription_type_id optional.',
    inputSchema: z.object({
      subscription_type_id: z.number().int().optional()
    })
  },
  async (a) =>
    textResult(
      await get('/mcp/template-env-variables', {
        subscription_type_id: a.subscription_type_id
      })
    )
);

server.registerTool(
  'get_template_env_variable',
  {
    description: 'Get one template env row by id.',
    inputSchema: z.object({ id: z.number().int() })
  },
  async (a) => textResult(await get(`/mcp/template-env-variables/${a.id}`))
);

server.registerTool(
  'create_template_env_variable',
  {
    description: 'POST create template row (subscription_type_id, key, value, requires_manual_fill, admin_label, help_text).',
    inputSchema: z.object({
      subscription_type_id: z.number().int(),
      key: z.string(),
      value: z.string().nullable().optional(),
      requires_manual_fill: z.boolean().optional(),
      admin_label: z.string().nullable().optional(),
      help_text: z.string().nullable().optional()
    })
  },
  async (a) => textResult(await post('/mcp/template-env-variables', stripUndef(a)))
);

server.registerTool(
  'update_template_env_variable',
  {
    description: 'PUT update template row by id (partial body).',
    inputSchema: z.object({
      id: z.number().int(),
      subscription_type_id: z.number().int().optional(),
      key: z.string().optional(),
      value: z.string().nullable().optional(),
      requires_manual_fill: z.boolean().optional(),
      admin_label: z.string().nullable().optional(),
      help_text: z.string().nullable().optional()
    })
  },
  async (a) => {
    const { id, ...body } = a;
    return textResult(
      await put(`/mcp/template-env-variables/${id}`, stripUndef(body))
    );
  }
);

server.registerTool(
  'delete_template_env_variable',
  {
    description: 'DELETE template row by id.',
    inputSchema: z.object({ id: z.number().int() })
  },
  async (a) => textResult(await del(`/mcp/template-env-variables/${a.id}`))
);

// --- Per-subscription env (EnvVariables model) ---

server.registerTool(
  'list_env_variables',
  {
    description: 'List env key/values for a customer_subscription_id.',
    inputSchema: z.object({ customer_subscription_id: z.number().int() })
  },
  async (a) =>
    textResult(
      await get('/mcp/env-variables', {
        customer_subscription_id: a.customer_subscription_id
      })
    )
);

server.registerTool(
  'get_env_variable',
  {
    description: 'Get one env row by id.',
    inputSchema: z.object({ id: z.number().int() })
  },
  async (a) => textResult(await get(`/mcp/env-variables/${a.id}`))
);

server.registerTool(
  'create_env_variable',
  {
    description: 'POST create env row for a subscription.',
    inputSchema: z.object({
      customer_subscription_id: z.number().int(),
      key: z.string(),
      value: z.string().nullable().optional()
    })
  },
  async (a) => textResult(await post('/mcp/env-variables', stripUndef(a)))
);

server.registerTool(
  'update_env_variable',
  {
    description: 'PUT update env row by id.',
    inputSchema: z.object({
      id: z.number().int(),
      key: z.string().optional(),
      value: z.string().nullable().optional()
    })
  },
  async (a) => {
    const { id, ...body } = a;
    return textResult(await put(`/mcp/env-variables/${id}`, stripUndef(body)));
  }
);

server.registerTool(
  'delete_env_variable',
  {
    description: 'DELETE env row by id.',
    inputSchema: z.object({ id: z.number().int() })
  },
  async (a) => textResult(await del(`/mcp/env-variables/${a.id}`))
);

// --- Customer ---

const customerFields = {
  company_name: z.string().optional(),
  max_users: z.number().int().optional(),
  docket_description: z.string().optional(),
  task_description: z.string().optional(),
  level_one_description: z.string().optional(),
  level_two_description: z.string().optional(),
  level_three_description: z.string().optional(),
  level_four_description: z.string().optional(),
  level_five_description: z.string().optional(),
  level_one_in_use: z.boolean().optional(),
  level_two_in_use: z.boolean().optional(),
  level_three_in_use: z.boolean().optional()
};

server.registerTool(
  'list_customers',
  {
    description: 'Paginated customers (no S3/api secrets).',
    inputSchema: z.object({
      page: z.number().int().min(1).optional(),
      per_page: z.number().int().min(1).max(100).optional()
    })
  },
  async (a) => textResult(await get('/mcp/customers', stripUndef(a)))
);

server.registerTool(
  'get_customer',
  {
    description: 'GET one customer by id.',
    inputSchema: z.object({ id: z.number().int() })
  },
  async (a) => textResult(await get(`/mcp/customers/${a.id}`))
);

server.registerTool(
  'create_customer',
  {
    description:
      'POST create customer. company_name required. Cannot set S3 or API token via MCP.',
    inputSchema: z.object({
      company_name: z.string(),
      ...customerFields
    })
  },
  async (a) => textResult(await post('/mcp/customers', stripUndef(a)))
);

server.registerTool(
  'update_customer',
  {
    description: 'PUT update customer (safe fields only).',
    inputSchema: z.object({
      id: z.number().int(),
      company_name: z.string().optional(),
      ...customerFields
    })
  },
  async (a) => {
    const { id, ...body } = a;
    return textResult(await put(`/mcp/customers/${id}`, stripUndef(body)));
  }
);

server.registerTool(
  'delete_customer',
  {
    description: 'DELETE (soft-delete) customer by id.',
    inputSchema: z.object({ id: z.number().int() })
  },
  async (a) => textResult(await del(`/mcp/customers/${a.id}`))
);

// --- CustomerSubscription ---

const subOptional = {
  server_id: z.number().int().nullable().optional(),
  logo_1: z.string().optional(),
  logo_2: z.string().optional(),
  logo_3: z.string().optional(),
  logo_4: z.string().optional(),
  logo_5: z.string().optional(),
  env: z.string().nullable().optional(),
  app_name: z.string().nullable().optional(),
  uuid: z.string().optional(),
  forge_site_id: z.string().nullable().optional(),
  site_created_at: z.string().optional(),
  github_sent_at: z.string().optional(),
  env_sent_at: z.string().optional(),
  deployment_script_sent_at: z.string().optional(),
  ssl_deployed_at: z.string().optional(),
  deployed_at: z.string().optional(),
  panic_button_enabled: z.boolean().optional(),
  deployed_version: z.string().nullable().optional()
};

const subUpdateFields = {
  url: z.string().optional(),
  domain: z.string().optional(),
  database_name: z.string().optional(),
  subscription_type_id: z.number().int().optional(),
  customer_id: z.number().int().optional(),
  ...subOptional
};

server.registerTool(
  'list_customer_subscriptions',
  {
    description: 'Paginated subscriptions; env blob omitted. Filters: customer_id, subscription_type_id.',
    inputSchema: z.object({
      customer_id: z.number().int().optional(),
      subscription_type_id: z.number().int().optional(),
      page: z.number().int().min(1).optional(),
      per_page: z.number().int().min(1).max(100).optional()
    })
  },
  async (a) => textResult(await get('/mcp/customer-subscriptions', stripUndef(a)))
);

server.registerTool(
  'get_customer_subscription',
  {
    description: 'GET one subscription. include_env true to return env JSON string.',
    inputSchema: z.object({
      id: z.number().int(),
      include_env: z.boolean().optional()
    })
  },
  async (a) =>
    textResult(
      await get(`/mcp/customer-subscriptions/${a.id}`, {
        include_env: a.include_env === true ? 1 : undefined
      })
    )
);

server.registerTool(
  'create_customer_subscription',
  {
    description:
      'POST create subscription. Required: url, domain, database_name, subscription_type_id, customer_id.',
    inputSchema: z.object({
      url: z.string(),
      domain: z.string(),
      database_name: z.string(),
      subscription_type_id: z.number().int(),
      customer_id: z.number().int(),
      include_env: z.boolean().optional(),
      ...subOptional
    })
  },
  async (a) => {
    const { include_env: includeEnv, ...body } = a;
    const q = includeEnv === true ? { include_env: 1 } : {};
    return textResult(
      await apiRequest('POST', '/mcp/customer-subscriptions', q, stripUndef(body))
    );
  }
);

server.registerTool(
  'update_customer_subscription',
  {
    description: 'PUT update subscription by id. include_env for response body.',
    inputSchema: z.object({
      id: z.number().int(),
      include_env: z.boolean().optional(),
      ...subUpdateFields
    })
  },
  async (a) => {
    const { id, include_env: includeEnv, ...body } = a;
    const q = includeEnv === true ? { include_env: 1 } : {};
    return textResult(
      await apiRequest('PUT', `/mcp/customer-subscriptions/${id}`, q, stripUndef(body))
    );
  }
);

server.registerTool(
  'delete_customer_subscription',
  {
    description: 'DELETE subscription by id.',
    inputSchema: z.object({ id: z.number().int() })
  },
  async (a) => textResult(await del(`/mcp/customer-subscriptions/${a.id}`))
);

function stripUndef (o) {
  return Object.fromEntries(
    Object.entries(o).filter(([, v]) => v !== undefined)
  );
}

async function main () {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
