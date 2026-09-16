# Frontend Rebuild Spec — Filament to Vue

Build contract for replacing the Filament admin panel at `/admin` with a custom Vue SPA in a separate repo.

**Target stack:** Vite SPA, `vue-router`, Pinia for auth and state, Tailwind with headless primitives (Headless UI or Radix Vue), own components.

**Data source:** the Sanctum-authenticated JSON API at `/api/backend`. Route definitions live in [`routes/backend-api.php`](../routes/backend-api.php); the endpoint contract is [`BACKEND_API.md`](BACKEND_API.md).

**Scope of this document.** Structure and behaviour only: pages, routes, columns, fields, validation, actions, modal copy, and permission gating. It deliberately makes no visual or design prescriptions — spacing, colour, typography, and component styling are the frontend's call.

**How to read the quirk flags.** Filament's current behaviour is documented as-is so nothing gets lost in translation. Where that behaviour looks like a bug, it is marked **Quirk** with a recommendation. Each is an independent decision; none are pre-approved. Section 8 collects them all.

---

## 1. App shell and auth

### 1.1 Navigation

Filament has exactly two navigation groups. There is no explicit ordering anywhere in the source (no resource sets `$navigationSort`, and `navigationGroups()` is never called), so today's order is alphabetical by label with Shield's Roles pinned first. Ordering in the new UI is therefore a fresh decision, not a port.

**Customers**
- Customers

**System Administration**
- Roles (Filament Shield, slug `shield/roles`, shows a count badge)
- Users
- Subscription Types
- Deployment Scripts
- Deployment Templates
- Template Env Variables (URL is `required-env-variables` — see the naming quirk in section 8)
- Forge Servers
- Nginx Templates

**Not in the sidebar.** These set `$shouldRegisterNavigation = false` and are reachable only by direct URL or from a parent page: Customer Subscriptions, Customer Users, User Customers, Env Variables, and the Deployment Pipeline Steps page.

Per the nested-only decision, Customer Users and User Customers are specced as tabs under Customer rather than standalone screens. Customer Subscriptions and Env Variables *do* get real top-level list pages, because subscriptions are the operational centre of the app and env variables need a cross-subscription view.

Every resource in Filament uses the same `heroicon-o-rectangle-stack` icon, so the sidebar is visually undifferentiated. Assigning distinct icons is an easy improvement.

### 1.2 Authentication

Three endpoints, all under `/api/backend`:

| Method | Path | Purpose |
| --- | --- | --- |
| `POST` | `/login` | `email` + `password`. Returns `{ data: { token, user } }`. Wrong credentials return `422` with `email: ["The provided credentials are incorrect."]` |
| `POST` | `/logout` | Revokes the current token, returns `{ ok: true }` |
| `GET` | `/user` | Returns the current user payload |

The token is a Sanctum personal access token with the ability `backend`. Send it as `Authorization: Bearer {token}` on every other request. Tokens do not expire (`sanctum.expiration` is `null`), so the app decides its own session lifetime.

The `user` payload — store this in Pinia at login and use it to drive gating:

```json
{
  "data": {
    "id": 1,
    "name": "Jane Admin",
    "email": "jane@example.com",
    "email_verified_at": null,
    "roles": ["super_admin"],
    "permissions": ["ViewAny:Customer", "Create:Customer", "..."]
  }
}
```

Unauthenticated requests return `401`; failed permission checks return `403`. Treat a `401` on any request as a session expiry and route to `/login`.

### 1.3 Login screen

Filament's stock login page, for reference. Copy is worth keeping since users know it.

| Element | Text |
| --- | --- |
| Heading | Sign in |
| Field 1 | Email address (required, autofocused) |
| Field 2 | Password (required, reveal toggle) |
| Field 3 | Remember me (checkbox) |
| Submit | Sign in |
| Link | Forgot password? |
| Failed login | These credentials do not match our records. |
| Throttled | Title "Too many login attempts", body "Please try again in :seconds seconds." |

There is **no registration** and **no profile page** — `->registration()` and `->profile()` are not enabled. The user menu contains only a theme switcher and Sign out. If a profile screen is wanted, it is new functionality and needs new endpoints.

**Password reset** is enabled in Filament but has **no `/api/backend` endpoints yet**. Either add them or drop the "Forgot password?" link. See section 9.

### 1.4 Permission gating

Authorization uses Filament Shield over Spatie permissions. The naming convention is `Ability:Model` in PascalCase with a colon — note `ViewAny:Customer`, not `view_any_customer`.

Abilities per model: `ViewAny`, `View`, `Create`, `Update`, `Delete`, `Restore`, `ForceDelete`, `ForceDeleteAny`, `RestoreAny`, `Replicate`, `Reorder`.

Models: `Customer`, `CustomerSubscription`, `CustomerUser`, `UserCustomer`, `User`, `SubscriptionType`, `DeploymentScript`, `DeploymentTemplate`, `EnvVariables`, `TemplateEnvVariables`, `ForgeServer`, `NginxTemplate`, `Role`.

Note two naming traps: the permission model segment for Required Env Variables is `TemplateEnvVariables`, and for Env Variables it is the plural `EnvVariables`.

Gating rules to mirror:

| UI element | Required permission |
| --- | --- |
| Sidebar item and list route | `ViewAny:{Model}` |
| Detail page | `View:{Model}` |
| "New" button and create route | `Create:{Model}` |
| Edit action and edit route | `Update:{Model}` |
| Delete action, single and bulk | `Delete:{Model}` |
| Restore action | `Restore:{Model}` |
| Force delete action | `ForceDelete:{Model}` |

The `super_admin` role bypasses every check (Shield intercepts the gate `before` resolution), so a super admin sees everything regardless of assigned permissions.

Gate on the client for UX only. The API enforces the same policies independently, so a hidden button is a convenience, not a security boundary.

### 1.5 Dashboard

Currently just two stock Filament widgets: `AccountWidget` (avatar, "Welcome", user name, sign-out button) and `FilamentInfoWidget` (Filament branding and version links). The second should simply be dropped.

There is no dashboard data endpoint. A useful dashboard is new design work — a reasonable starting point would be recent deployment job failures and subscriptions with an incomplete pipeline, both derivable from existing endpoints.

### 1.6 Global search

Filament exposes global search in the topbar. Searchable resources today:

| Resource | Searched on |
| --- | --- |
| Customers | `name` (broken — see quirk) |
| Subscription Types | `name` |
| Nginx Templates | `name` |
| Deployment Templates | `subscriptionType.name` |
| Template Env Variables | `subscriptionType.name` |

Results are permission-filtered per resource by the same `ViewAny:{Model}` check.

**There is no global search endpoint** in `/api/backend`. Either add one or scope search to per-list filtering in the first release. Also note three of the five searchable resources do not set `$recordTitleAttribute`, so their result rows currently render the model label instead of the record name.

---

## 2. Route map

Filament URL to proposed Vue route. All Filament paths are prefixed `/admin`.

| Filament | Vue route | Name | Notes |
| --- | --- | --- | --- |
| `/login` | `/login` | `login` | Public |
| `/` | `/` | `dashboard` | |
| `/customers` | `/customers` | `customers.index` | |
| `/customers/create` | `/customers/new` | `customers.create` | |
| `/customers/{id}` | `/customers/:id` | `customers.show` | Detail with nested tabs |
| `/customers/{id}/edit` | `/customers/:id/edit` | `customers.edit` | |
| `/customer-subscriptions` | `/subscriptions` | `subscriptions.index` | |
| `/customer-subscriptions/create` | `/subscriptions/new` | `subscriptions.create` | Plain form — see 6.3 |
| `/customer-subscriptions/{id}` | `/subscriptions/:id` | `subscriptions.show` | |
| `/customer-subscriptions/{id}/edit` | `/subscriptions/:id/edit` | `subscriptions.edit` | The operational cockpit |
| `/customer-subscriptions/{id}/deployment-pipeline-steps` | `/subscriptions/:id/pipeline` | `subscriptions.pipeline` | |
| `/users` | `/users` | `users.index` | + `/new`, `/:id/edit` |
| `/subscription-types` | `/subscription-types` | `subscriptionTypes.index` | + `/new`, `/:id/edit` |
| `/deployment-scripts` | `/deployment-scripts` | `deploymentScripts.index` | + `/new`, `/:id/edit` |
| `/deployment-templates` | `/deployment-templates` | `deploymentTemplates.index` | + `/new`, `/:id/edit` |
| `/env-variables` | `/env-variables` | `envVariables.index` | + `/new`, `/:id/edit` |
| `/required-env-variables` | `/template-env-variables` | `templateEnvVariables.index` | + `/new`, `/:id/edit`. Renamed for consistency with the model and permissions |
| `/forge-servers` | `/forge-servers` | `forgeServers.index` | + `/new`, `/:id/edit` |
| `/nginx-templates` | `/nginx-templates` | `nginxTemplates.index` | + `/new`, `/:id/edit` |
| `/customer-users` | *(none)* | | Nested tab under Customer |
| `/user-customers` | *(none)* | | Nested tab under Customer |

Every route except `/login` requires a valid token. Guard each with its `ViewAny:{Model}` permission and redirect to the dashboard with a message on failure.

---

## 3. Shared behaviour

These are Filament framework defaults. They do not appear anywhere in the application source but they are visible in the UI, so they are easy to drop by accident.

### 3.1 Tables

| Concern | Behaviour |
| --- | --- |
| Page size | Selector with `5, 10, 25, 50`; default **10**. No "all" option |
| Default sort | **None on any table.** Rows arrive in primary-key order |
| Search | One search box in the table header, placeholder `Search`, matching across all searchable columns. No per-column inputs |
| Result count | `:count result` / `:count results`; `No results` when empty |
| Empty state | Heading `No {plural model label}`, body `Create a {model label} to get started.` |
| Column visibility | Toggleable columns sit behind a "Column manager" dropdown (heading `Columns`, buttons `Apply columns` and `Reset`) |
| Row selection | Checkbox column appears whenever bulk actions exist. Indicator reads `:count records selected` with `Select all :count` and `Deselect all` |
| Bulk trigger | A `Bulk actions` dropdown button |

The API paginates with `per_page` (default **25**, max 100) and `page`. Note the mismatch: Filament defaults to 10 per page, the API to 25. Pick one deliberately.

Soft-delete filtering maps to a `trashed` query parameter accepting `with` or `only`; omitting it returns only live records. Available on Customers, Customer Users, User Customers, and Subscription Types.

**Two capabilities the API does not yet have.** No endpoint accepts a `search` parameter, and none accepts a sort parameter — every list returns a fixed order (`id` ascending, or `key` for env variables). So the search box and sortable column headers documented throughout section 4 **cannot be built against the current API**. Both are listed in section 9. Until they exist, either add the parameters server-side or accept that filtering and sorting only work within the current page, which degrades badly past a few hundred records.

The per-resource filters that *do* exist:

| Endpoint | Filters |
| --- | --- |
| `/customers` | `trashed` |
| `/customer-subscriptions` | `customer_id`, `subscription_type_id` |
| `/customer-users` | `customer_id`, `trashed` |
| `/user-customers` | `user_id`, `customer_id`, `trashed` |
| `/subscription-types` | `trashed` |
| `/env-variables`, `/deployment-scripts` | `customer_subscription_id` |
| `/deployment-templates`, `/template-env-variables` | `subscription_type_id` |
| `/users`, `/forge-servers`, `/nginx-templates` | none beyond pagination |

### 3.2 Formatting

| Type | Rendering |
| --- | --- |
| `dateTime()` columns | `M j, Y H:i:s`, e.g. `Sep 9, 2026 14:05:00` |
| Boolean icon columns | True is a green check-circle, false a red x-circle |
| Relative-time placeholders | `diffForHumans()`, e.g. "2 days ago", falling back to `-` |
| Empty values | Mixed today: some entries use `-`, others `—`. Normalise deliberately |

Labels in Filament are auto-generated from the field name (underscores to spaces, first letter capitalised), which is where headers like `Email address` and `Time and attendance access` come from. It is also the source of several ambiguous headers: `customer.id` renders as just `Id` and `user.name` as just `Name`, because Filament drops the relationship prefix. Those are called out per table below.

### 3.3 Toasts

| Event | Text |
| --- | --- |
| Create | Created |
| Save | Saved |
| Delete | Deleted |
| Force delete | Deleted |
| Restore | Restored |

Partial bulk failures use `Deleted :count of :total` with body `:count could not be deleted.` and a permission variant `You don't have permission to delete :count.` Restore has equivalents.

### 3.4 Confirmation modals

All destructive and restore actions confirm. The cancel button is always `Cancel`.

| Action | Heading | Body | Submit |
| --- | --- | --- | --- |
| Delete | `Delete {record}` | `Are you sure you would like to do this?` | Delete |
| Force delete | `Force delete {record}` | same | Delete |
| Restore | `Restore {record}` | same | Restore |
| Bulk delete | `Delete selected {plural}` | same | Delete |
| Bulk force delete | `Force delete selected {plural}` | same | Delete |
| Bulk restore | `Restore selected {plural}` | same | Restore |

`{record}` is the record title. Custom actions with their own copy are documented at each action.

### 3.5 Soft-delete action visibility

For soft-deletable models — Customer, CustomerUser, UserCustomer, SubscriptionType:

- **Delete** is hidden once the record is already trashed
- **Restore** and **Force delete** appear *only* when the record is trashed

User, CustomerSubscription, and the six config models are not soft-deletable, so delete is always available and is permanent. Restore and force-delete do not exist for them.

Soft-deleted records remain reachable by direct URL on Customers, Customer Users, User Customers, and Subscription Types, because those resources drop the soft-delete scope from route binding. The detail page should make a trashed state obvious.

### 3.6 Form layout and redirects

Filament renders resource forms in a **two-column responsive grid** by default, fields flowing left to right. A `Section` spans the full width and stacks its own children in one column. Only one field in the whole app sets `columnSpanFull()`.

Redirect behaviour to match:

| Event | Destination |
| --- | --- |
| After create | The detail page where the resource has one (Customers, Subscriptions), otherwise the edit page |
| After save on edit | Stays on the edit page |
| After delete from an edit page | The list page |
| "Create & create another" | Stays on a blank create form |

Create pages have three footer buttons: `Create`, `Create & create another`, `Cancel`. Edit pages have `Save changes` and `Cancel`.

---

## 4. Page-by-page inventory

### 4.1 Customers

Model `Customer`, soft-deletes. Permissions `*:Customer`. Endpoints `/customers`.

**Model side effects** the UI should account for:
- On create, a blank `token` is auto-filled with a UUID, and `uuid` is generated. Both fields are effectively system-managed even though the form exposes them.
- On update, changing any level description, level in-use flag, `task_description`, or `docket_description` dispatches `SendSystemConfigJob`, pushing config to the customer's live site. This happens silently with no toast. Worth surfacing in the new UI, since it has real external effect.

#### List

Heading "Customers". Header action `New customer`.

| # | Field | Label | Type | Search | Sort | Toggle |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `company_name` | Company name | text | yes | no | no |
| 2 | `google_api_key` | Google API key | boolean icon | no | no | no |
| 3 | `s3_configured` | S3 / MinIO | boolean icon | no | no | no |
| 4 | `deleted_at` | Deleted at | dateTime | no | yes | hidden by default |
| 5 | `created_at` | Created at | dateTime | no | yes | hidden by default |
| 6 | `updated_at` | Updated at | dateTime | no | yes | hidden by default |
| 7 | `token` | Token | text | yes | no | no |
| 8 | `docket_description` | Docket description | text | yes | no | no |
| 9 | `task_description` | Task description | text | yes | no | no |
| 10-14 | `level_one_description` … `level_five_description` | Level one/two/three/four/five description | text | yes | no | no |
| 15-17 | `level_one_in_use` … `level_three_in_use` | Level one/two/three in use | boolean icon | no | no | no |
| 18 | `max_users` | Max users | numeric | no | yes | no |
| 19 | `uuid` | UUID | text | yes | no | no |

Columns 2 and 3 are computed, not raw values. `google_api_key` renders a check when a key is stored and never exposes it. `s3_configured` is virtual and true only when **all four** of `s3_endpoint`, `s3_key`, `s3_secret`, and `s3_bucket` are filled.

**Quirk.** Column 7 prints the raw API `token` in clear text. The API hides it on read, so the new UI cannot reproduce this even if it wanted to. Drop the column.

**Filter.** Trashed, label "Deleted records", options: blank means "Without deleted records" (default), "With deleted records", "Only deleted records". Maps to `trashed=with|only`.

**Row actions.** View, Edit. **Bulk actions.** Delete selected, Force delete selected, Restore selected.

#### Create and edit form

Flat two-column grid with one full-width section in the middle.

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `company_name` | text | Company name | yes | |
| 2 | `google_api_key` | password, revealable | Google API key | no | Masked display only; stored in plain text |

**Section "S3 / MinIO storage"**, collapsible and expanded by default. Description: "S3-compatible object storage (e.g. MinIO). Use the same values as Laravel's s3 disk: endpoint, access key, secret, region, bucket, and path-style endpoint for MinIO."

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 3 | `s3_endpoint` | text, url | Endpoint URL | no | Placeholder `http://127.0.0.1:9005` |
| 4 | `s3_key` | text | Access key | no | |
| 5 | `s3_secret` | password, revealable | Secret key | no | |
| 6 | `s3_region` | text | Region | no | Placeholder `us-east-1` |
| 7 | `s3_bucket` | text | Bucket | no | |
| 8 | `s3_use_path_style_endpoint` | toggle | Path-style endpoint | no | Default **on**. Helper: "Required for most MinIO setups (see Laravel filesystems s3 disk)." |

Back to the top level:

| # | Field | Type | Label | Required | Default |
| --- | --- | --- | --- | --- | --- |
| 9 | `token` | text | Token | no | Auto-filled with a UUID when blank |
| 10 | `docket_description` | text | Docket description | yes | `Docket` |
| 11 | `task_description` | text | Task description | yes | `Task` |
| 12 | `level_one_description` | text | Level one description | yes | `Level 1` |
| 13 | `level_two_description` | text | Level two description | yes | `Level 2` |
| 14 | `level_three_description` | text | Level three description | yes | `Level 3` |
| 15 | `level_four_description` | text | Level four description | yes | `Level 4` |
| 16 | `level_five_description` | text | Level five description | yes | `Level 5` |
| 17 | `level_one_in_use` | toggle | Level one in use | — | |
| 18 | `level_two_in_use` | toggle | Level two in use | — | |
| 19 | `level_three_in_use` | toggle | Level three in use | — | |
| 20 | `max_users` | numeric | Max users | yes | `1` |
| 21 | `uuid` | text | UUID | no | System-generated when blank |

The three in-use toggles are marked `required()` in Filament, which on a boolean means "must be on". Treat them as plain booleans.

**Blocking gap.** The API hides `token`, `google_api_key`, and all five `s3_*` fields on every read, unconditionally. The Vue app can *write* them but cannot *read* them, so an edit form cannot show current values and any save risks silently blanking. This needs an `include_secrets` option or a dedicated credentials endpoint before the S3 section can be built. See section 9.

**API note.** `uuid` is not in the model's `$fillable`, so it cannot be set through the API even though the Filament form exposes it.

#### Detail page

Read-only view of all customer fields, followed by the nested tabs in section 5.1. Secrets are masked as `********` when present and `—` when empty for `google_api_key`, `s3_key`, and `s3_secret`. `deleted_at` shows only when the record is trashed. Header action: Edit.

**Quirk.** `token` is shown in clear text here too. The API hides it, so omit it.

**Quirk.** The resource sets `$recordTitleAttribute = 'name'`, but `customers` has no `name` column — it is `company_name`. Every modal heading that interpolates a customer title currently renders blank. Use `company_name`.

### 4.2 Customer Subscriptions

Model `CustomerSubscription`, **no soft deletes** — every delete is permanent. Permissions `*:CustomerSubscription`. Endpoints `/customer-subscriptions`.

Hidden from Filament's sidebar today, but it gets a real list page in the new UI.

#### List

Heading "Customer Subscriptions". Header action `New customer subscription`.

This table has 23 columns, most visible by default, so it scrolls horizontally. Trimming it is recommended.

| # | Field | Label | Type | Search | Sort | Toggle |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `url` | Url | text | yes | no | no |
| 2 | `subscriptionType.name` | Subscription type | text | yes | no | no |
| 3-7 | `logo_1` … `logo_5` | Logo 1 … Logo 5 | **text path, not an image** | yes | no | no |
| 8 | `customer.id` | Customer | numeric id | yes | no | no |
| 9 | `created_at` | Created at | dateTime | no | yes | hidden |
| 10 | `updated_at` | Updated at | dateTime | no | yes | hidden |
| 11 | `forge_site_id` | Forge site | text | yes | no | no |
| 12 | `server_id` | Server | numeric | no | yes | no |
| 13 | `app_name` | App name | text | yes | no | no |
| 14 | `database_name` | Database name | text | yes | no | no |
| 15 | `database_user` | DB user | text | yes | no | yes, visible |
| 16 | `site_created_at` | Site created at | dateTime | no | yes | no |
| 17 | `github_sent_at` | Github sent at | dateTime | no | yes | no |
| 18 | `env_sent_at` | Env sent at | dateTime | no | yes | no |
| 19 | `deployment_script_sent_at` | Deployment script sent at | dateTime | no | yes | no |
| 20 | `ssl_deployed_at` | Ssl deployed at | dateTime | no | yes | no |
| 21 | `deployed_at` | Deployed at | dateTime | no | yes | no |
| 22 | `domain` | Domain | text | yes | no | no |
| 23 | `panic_button_enabled` | Panic button enabled | boolean icon | no | no | no |

Columns 16 to 21 are a **deployment progress timeline** rendered as six separate raw timestamps. Collapsing them into one progress or status indicator is the single biggest readability win available in this rebuild.

Columns 3 to 7 print raw storage paths. Render thumbnails instead.

**Filter.** Subscription type, a searchable preloaded select over all subscription types. Maps to `subscription_type_id`.

**Row actions.** Edit only — there is no view action despite a detail page existing. **Bulk actions.** Delete selected (permanent).

#### Form (used by create and edit)

Flat two-column grid, no sections.

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `url` | text | Url | yes | No uniqueness or format validation here, unlike the guided flow |
| 2 | `domain` | text | Domain | yes | |
| 3 | `app_name` | text | App name | yes | |
| 4 | `customer_id` | select | Customer | yes | Options are company names. Not searchable or preloaded |
| 5 | `subscription_type_id` | select | Subscription type | yes | Options are type names |
| 6 | `deployed_version` | text | Deployed version | no | Max 8 characters |
| 7-11 | `logo_1` … `logo_5` | file upload | **dynamic, see below** | no | Public disk, max 10 MB, downloadable. No type restriction and no image validation |
| 12 | `database_name` | text | Database name | no | |
| 13 | `database_user` | text | Database user (MySQL) | no | Max 32. Helper: "Used for Forge DB user and DB_USERNAME. Max 32 characters (MySQL limit). Leave blank to match the database name (truncated if needed)." |
| 14 | `forge_site_id` | text | Forge site | no | |
| 15 | `panic_button_enabled` | toggle | Panic Button | no | |
| 16 | `created_at` | read-only | Created Date | — | Relative time, `-` when empty |
| 17 | `updated_at` | read-only | Last Modified Date | — | Relative time, `-` when empty |

**Logo labels are dynamic** on `subscription_type_id`:

| Slot | Type 3 (responder) | All other types |
| --- | --- | --- |
| `logo_1` | App Logo | Login Logo |
| `logo_2` | Home Logo | Menu Logo |
| `logo_3` | Login Logo | Login Background |
| `logo_4` | Not Used | Not Used |
| `logo_5` | Not Used | Not Used |

**Quirk.** `subscription_type_id` is not reactive on this form, so the logo labels only refresh after a save and reload. Make the labels live in Vue.

Slots 4 and 5 are labelled "Not Used" for every type. Consider hiding them.

**Model side effect.** On create, PHP-project subscription types with a `database_name` get a randomly generated 32-character `database_password`, and `database_user` is derived from the normalised database name when left blank.

#### Detail page

**Quirk.** The resource defines no infolist, so Filament falls back to rendering the form in disabled mode. A dedicated read-only page is better. `CustomerSubscriptionInfolist.php` exists but is referenced nowhere — it is dead code, and it is the better reference for what a detail page should show, notably including a full-width raw `env` dump the form omits.

Header action: Edit. Below it, the three nested tabs in section 5.2.

#### Edit page

The form above, plus the action cluster documented in section 6.1 and the three nested tabs.

### 4.3 Users

Model `User`, **no soft deletes**. Permissions `*:User`. Endpoints `/users`.

#### List

Heading "Users". Header action `New user`.

| # | Field | Label | Type | Search | Sort | Toggle |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `name` | Name | text | yes | no | no |
| 2 | `email` | Email address | text | yes | no | no |
| 3 | `email_verified_at` | Email verified at | dateTime | no | yes | no |
| 4 | `created_at` | Created at | dateTime | no | yes | hidden |
| 5 | `updated_at` | Updated at | dateTime | no | yes | hidden |

No filters. **Row actions.** Edit. **Bulk actions.** Delete selected.

#### Form

Two full-width sections.

**Section "User information"**

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `name` | text | Name | yes | Max 255 |
| 2 | `email` | email | Email | yes | Max 255. **No unique rule** |
| 3 | `email_verified_at` | datetime picker | Email verified at | no | |
| 4 | `password` | password | Password | yes | Max 255 |

**Section "Roles and Permissions"**

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 5 | `roles` | multi-select | Roles | no | Spatie roles, preloaded, saved as a sync |

Hashing is handled by the model's `hashed` cast, so submit the plain value.

**Quirk.** `password` is required on edit as well as create, because both operations share one schema with no create-only guard. Every user edit currently demands a new password. Make it optional on edit and ignore it when blank.

**Quirk.** No unique validation on `email`. A duplicate hits the database unique index and surfaces as a server error rather than a field error. Add a unique rule ignoring the current record.

**Gap.** The `roles` multi-select needs a list of available roles, and there is no `GET /roles` endpoint. See section 9.

### 4.4 Subscription Types

Model `SubscriptionType`, soft-deletes. Permissions `*:SubscriptionType`. Endpoints `/subscription-types`.

#### List

Heading "Subscription Types". Header action `New subscription type`.

| # | Field | Label | Type | Search | Sort |
| --- | --- | --- | --- | --- | --- |
| 1 | `id` | Id | text | no | no |
| 2 | `name` | Name | text | yes | yes |
| 3 | `github_repo` | Github repo | text | yes | yes |
| 4 | `branch` | Branch | text | yes | yes |
| 5 | `project_type` | Project type | text | yes | yes |
| 6 | `master_version` | Master version | text | yes | yes |

**Filter.** Trashed, as described in 3.1.

**Row actions.** This is the only resource with the full soft-delete set inline: Edit, then Delete when live, then Restore and Force delete when trashed. **Bulk actions.** Delete selected, Restore selected, Force delete selected.

#### Form

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `name` | text | Name | yes | |
| 2 | `github_repo` | text | Github repo | yes | Free text, no URL validation |
| 3 | `branch` | text | Branch | yes | |
| 4 | `project_type` | text | Project type | yes | **Free text**, though behaviour keys off the value `php`. Should be a select |
| 5 | `master_version` | text | Master version | no | Max 8 |
| 6 | `created_at` | read-only | Created Date | — | Relative time |
| 7 | `updated_at` | read-only | Last Modified Date | — | Relative time |

`project_type` drives real logic — PHP projects get database provisioning steps in the deployment pipeline — so free text here is risky. A select constrained to known values is recommended.

`public_dir` and `nginx_template_id` exist on the model but are absent from this form.

**Subscription type IDs** are referenced throughout the app and worth documenting in the UI layer:

| ID | Type | ID | Type |
| --- | --- | --- | --- |
| 1 | console | 7 | survey |
| 2 | firearm | 8 | do not use |
| 3 | responder | 9 | time and attendance |
| 4 | reporter | 10 | stock |
| 5 | security | 11 | information |
| 6 | driver | | |

IDs 3 to 7 are "app type" subscriptions, which is what enables the panic-button toggle. IDs 1, 2, 9, 10, and 11 get the six extra Forge command steps in the deployment pipeline.

### 4.5 Deployment Scripts

Model `DeploymentScript`. Permissions `*:DeploymentScript`. Endpoints `/deployment-scripts`.

**List** — heading "Deployment Scripts", header action `New deployment script`.

| # | Field | Label | Type | Search | Sort |
| --- | --- | --- | --- | --- | --- |
| 1 | `script` | Script | text | no | no |
| 2 | `customer_subscription_id` | Customer Subscription Id | text | no | no |

No filters. Row actions Edit and Delete. Bulk action Delete selected.

**Quirk.** `script` is a `longText` shell script rendered as a single-line cell with no truncation. Truncate it and show the full value on the detail page.

**Form**

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `script` | textarea | Script | yes | No row count, no monospace, not full width, despite being a `longText` shell script |
| 2 | `customer_subscription_id` | **numeric text input** | Customer Subscription Id | yes | Raw integer entry |
| 3 | `created_at` | read-only | Created Date | — | |
| 4 | `updated_at` | read-only | Last Modified Date | — | |

**Quirk, two parts.** `script` wants a full-width code editor with monospace and syntax highlighting. And `customer_subscription_id` should be a searchable select — the `belongsTo` relation already exists; the form just does not use it. The API supports filtering by `customer_subscription_id` for populating it.

### 4.6 Deployment Templates

Model `DeploymentTemplate`. Permissions `*:DeploymentTemplate`. Endpoints `/deployment-templates`.

**List** — heading "Deployment Templates", header action `New deployment template`.

| # | Field | Label | Type | Search | Sort |
| --- | --- | --- | --- | --- | --- |
| 1 | `script` | Script | text | no | no |
| 2 | `subscriptionType.name` | Subscription Type | text | yes | yes |

No filters. Row actions Edit and Delete. Bulk action Delete selected.

**Form**

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `script` | textarea | Script | yes | Same code-editor quirk as 4.5 |
| 2 | `subscription_type_id` | select, searchable | Subscription Type | yes | Options are type names |
| 3 | `created_at` | read-only | Created Date | — | |
| 4 | `updated_at` | read-only | Last Modified Date | — | |

### 4.7 Env Variables

Model `EnvVariables`. Permissions `*:EnvVariables` (note the plural). Endpoints `/env-variables`.

Hidden from Filament's sidebar, since per-subscription editing happens in the nested tabs. A top-level list is still useful for cross-subscription work.

**List** — heading "Env Variables", header action `New env variables`.

| # | Field | Label | Type | Search | Sort | Toggle |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `key` | Key | text | yes | no | no |
| 2 | `value` | Value | text | yes | no | no |
| 3 | `customerSubscription.id` | Id | text | yes | no | no |
| 4 | `created_at` | Created At | dateTime | no | yes | hidden |
| 5 | `updated_at` | Updated At | dateTime | no | yes | hidden |

No filters. **Row actions.** Edit only. **Bulk actions.** Delete selected.

**Quirk.** There is no per-row delete, so removing one variable means selecting a checkbox and using the bulk menu, or opening the edit page. Add a row delete action.

**Quirk.** Column 2 renders secret values in clear text. Mask by default with a reveal control.

**Quirk.** Column 3's header is just `Id`. Label it "Subscription" and show the URL rather than the numeric id.

**Form**

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `key` | text | Key | yes | |
| 2 | `value` | text | Value | no | |
| 3 | `customer_subscription_id` | **numeric text input** | Customer Subscription Id | yes | Should be a searchable select |
| 4 | `created_at` | read-only | Created Date | — | |
| 5 | `updated_at` | read-only | Last Modified Date | — | |

### 4.8 Template Env Variables

Model `TemplateEnvVariables`, table `required_env_variables`. Permissions `*:TemplateEnvVariables`. Endpoints `/template-env-variables`.

These rows are the template that seeds each subscription's env variables. When `requires_manual_fill` is on, the value is left empty on each new subscription for an operator to fill; otherwise the default value is copied.

**List** — Filament's nav label is "Template Env Variables" while the URL is `required-env-variables`. Header action `New template env variables`.

| # | Field | Label | Type | Search | Sort | Toggle |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `key` | Key | text | yes | no | no |
| 2 | `value` | Value | text, truncated to 32 with full value on hover | no | no | no |
| 3 | `requires_manual_fill` | **Manual** | boolean icon | no | no | no |
| 4 | `admin_label` | Admin Label | text | no | no | yes, visible |
| 5 | `subscriptionType.name` | Subscription Type | text | no | yes | no |

**Filter.** Label "Product", a select over all subscription types by name, mapping to `subscription_type_id`.

**Quirk.** The filter has no empty guard, so clearing it applies `where(subscription_type_id, null)` and returns zero rows. Skip the parameter entirely when no value is selected.

**Row actions.** Edit, Delete. **Bulk actions.** Delete selected.

**Form**

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `key` | text | Key | yes | |
| 2 | `requires_manual_fill` | toggle | Requires manual fill per subscription | no | Default off. **Reactive** — drives field 3. Helper: "When enabled, the value is left empty on each customer subscription until an operator fills it (shown under Customer Subscription → Manual environment variables)." |
| 3 | `value` | text | Default template value | **conditional** | Required only when `requires_manual_fill` is off. Helper: "Not used when \"Requires manual fill\" is on; optional in that case." |
| 4 | `admin_label` | text | Admin label | no | Max 255. Helper: "Friendly label in the subscription manual-env table; defaults to the key." |
| 5 | `help_text` | textarea, 2 rows, **full width** | Help text | no | Shown as a hint when an operator fills the value |
| 6 | `subscription_type_id` | select | Subscription Type | yes | Not searchable today; make it searchable for consistency |
| 7 | `created_at` | read-only | Created Date | — | |
| 8 | `updated_at` | read-only | Last Modified Date | — | |

Field 2 driving field 3's required state is the only genuinely reactive validation in the app. Preserve it.

### 4.9 Forge Servers

Model `ForgeServer`, table `my_forge_servers`. Permissions `*:ForgeServer`. Endpoints `/forge-servers`.

**List** — heading "Forge Servers". Header actions in order: `Sync from Forge`, then `New forge server`.

| # | Field | Label | Type | Search | Sort | Toggle |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `forge_server_id` | Forge Server Id | numeric | no | yes | no |
| 2 | `name` | Name | text | yes | no | no |
| 3 | `ip_address` | Ip Address | text | yes | no | no |
| 4 | `created_at` | Created At | dateTime | no | yes | hidden |
| 5 | `updated_at` | Updated At | dateTime | no | yes | hidden |

No filters. **Row actions.** Edit only — same missing-row-delete quirk as 4.7. **Bulk actions.** Delete selected.

**Sync from Forge action**

| Property | Value |
| --- | --- |
| Label | Sync from Forge |
| Confirmation heading | Sync servers from Laravel Forge |
| Description | "Fetches all servers your Forge API key can access and creates or updates rows in the database. Existing rows are matched by Forge server ID." |
| Submit | Sync now |
| Modal fields | None |
| Endpoint | `POST /forge-servers/sync` |
| Success toast | Title "Forge servers synced", body "1 server was saved." or "{count} servers were saved." |
| Failure toast | Title "Could not sync servers", body is the error message |

It matches on `forge_server_id` and **never deletes**, so servers removed from Forge linger as stale rows.

**Two quirks.** The action has no authorization check at all in Filament — anyone who can reach the page can trigger a full sync. Gate it on `Create:ForgeServer` or a dedicated permission. And it runs synchronously, making one HTTP round trip per server inside the request, so the UI needs a loading state and the work should ideally move to a queue.

Failure requires a configured `FORGE_API_KEY`; without one the error reads "Laravel Forge is not configured: set FORGE_API_KEY in your .env…".

**Form**

| # | Field | Type | Label | Required |
| --- | --- | --- | --- | --- |
| 1 | `forge_server_id` | numeric | Forge Server Id | yes |
| 2 | `name` | text | Name | no |
| 3 | `ip_address` | text | Ip Address | no |
| 4 | `created_at` | read-only | Created Date | — |
| 5 | `updated_at` | read-only | Last Modified Date | — |

No uniqueness rule on `forge_server_id` and no database unique index, even though the sync matches on it. Duplicates are possible. Adding a unique rule is recommended.

### 4.10 Nginx Templates

Model `NginxTemplate`. Permissions `*:NginxTemplate`. Endpoints `/nginx-templates`.

**List** — heading "Nginx Templates", header action `New nginx template`.

| # | Field | Label | Type | Search | Sort |
| --- | --- | --- | --- | --- | --- |
| 1 | `name` | Name | text | yes | yes |
| 2 | `template_id` | Template Id | text | no | no |

`server_id` is absent from the table despite being a required field. Adding it is recommended.

No filters. Row actions Edit and Delete. Bulk action Delete selected.

**Form**

| # | Field | Type | Label | Required | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `name` | text | Name | yes | |
| 2 | `server_id` | select | Server | yes | Options from Forge servers by name. **The value is Forge's server id, not the local primary key** |
| 3 | `template_id` | numeric | Template Id | yes | The raw Forge nginx template id, typed by hand |
| 4 | `created_at` | read-only | Created Date | — | |
| 5 | `updated_at` | read-only | Last Modified Date | — | |

`server_id` is stored as a string containing a Forge numeric id and is not a foreign key to `my_forge_servers.id`. Keep that in mind when populating the select — use `forge_server_id` as the option value.

---

## 5. Nested tabs

### 5.1 Under a Customer

Both tabs appear on the customer detail and edit pages. Filament explicitly force-enables create, edit, and delete on them even from the read-only view page, so they are fully interactive in both places.

#### Tab "Customer Subscriptions"

| # | Field | Label | Type | Search | Sort |
| --- | --- | --- | --- | --- | --- |
| 1 | `subscriptionType.name` | Subscription Type | text | no | yes |
| 2 | `url` | URL | text | yes | no |
| 3 | `deployed_version` | Deployed Version | text | yes | yes |
| 4 | `panic_button_enabled` | Panic Button | **inline toggle** | no | no |

The panic-button toggle writes immediately on click, with no confirmation. It is **disabled unless** the row's `subscription_type_id` is in 3 to 7. Maps to `PUT /customer-subscriptions/{id}`.

**Filter.** Subscription Type select.

**Header action.** "Create Subscription" — the guided flow in section 6.2.

**Row actions.** Edit, Navigate (goes to the full subscription edit page), Delete (permanent).

**Quirk.** The Edit row action has no form defined on the relation manager, so Filament falls back to rendering the **Customer** form inside the modal — editing a subscription shows customer fields. Almost certainly a bug. Either give it a proper subscription form or drop the action in favour of Navigate.

**Bulk actions.** Delete selected.

#### Tab "Customer Users"

Endpoints `/customer-users`, filtered by `customer_id`.

| # | Field | Label | Type | Search |
| --- | --- | --- | --- | --- |
| 1 | `email_address` | Email | text | yes |
| 2 | `first_name` | First Name | text | yes |
| 3 | `last_name` | Last Name | text | yes |
| 4 | `cellphone` | Cellphone | text | yes |

No filters, no default sort.

**Form** (used by both the create and edit modals)

**Section "User Information"**, one column:

| # | Field | Type | Label | Required | Validation |
| --- | --- | --- | --- | --- | --- |
| 1 | `customer_id` | select, searchable | Customer | yes | Defaults to the parent customer |
| 2 | `first_name` | text | First name | yes | |
| 3 | `last_name` | text | Last name | yes | |
| 4 | `cellphone` | **international phone input** with country flags | Cellphone | yes | Unique per `customer_id`, ignoring the current record |
| 5 | `email_address` | email | Email address | yes | Unique per `customer_id`, ignoring the current record |
| 6 | `password` | password | Password | yes | Minimum 6 |

**Section "Access Rights"**, two columns:

| # | Field | Label |
| --- | --- | --- |
| 7 | `is_system_admin` | Is Super Admin |
| 8 | `console_access` | Console access |
| 9 | `firearm_access` | Firearm access |
| 10 | `responder_access` | Responder access |
| 11 | `reporter_access` | Reporter access |
| 12 | `security_access` | Security access |
| 13 | `survey_access` | Survey access |
| 14 | `time_and_attendance_access` | Time and attendance access |
| 15 | `stock_access` | Stock access |

**Quirk.** `driver_access` exists on the model and is honoured by the login-email logic, but is **missing from this form**, so it can never be granted here. Add it.

Passwords are hashed by a model mutator, so submit the plain value.

**Row actions**

| Action | Label | Modal | Behaviour | Endpoint |
| --- | --- | --- | --- | --- |
| Edit | Edit | Full form above | Saves, toast "Saved" | `PUT /customer-users/{id}` |
| Delete | Delete | Standard confirm | Soft delete | `DELETE /customer-users/{id}` |
| Update password | Update Password | `new_password` ("New Password") and `confirm_password` ("Confirm Password"), both required, min 6 | Mismatch shows an error toast "Passwords do not match" and saves nothing. Success toast "Password updated successfully" | `POST /customer-users/{id}/update-password` |
| Send welcome email | Send Welcome Email | **None — fires immediately** | Queues the welcome email. **No confirmation and no toast** | `POST /customer-users/{id}/send-welcome-email` |
| Send login email | Send Login Email | `subscription_type_id` select, "Subscription Type", required | Sends login details for that product. No success toast. Errors: "User does not have access to this subscription", or "Subscription not found" | `POST /customer-users/{id}/send-login-email` |
| Manage access rights | Manage Access Rights | Section "Access Rights", two columns, nine toggles labelled Super Admin, Console Access, Firearm Access, Responder Access, Reporter Access, Security Access, Survey Access, Time and Attendance Access, Stock Access. Pre-filled from the record | Success toast "Access rights updated successfully" | `PUT /customer-users/{id}/access-rights` |

**Quirk.** Send Welcome Email fires with no confirmation and gives no feedback, so a user cannot tell whether it worked. Add both.

**Bulk actions.** Delete selected, and a bulk Send Login Email with the same subscription-type select. Per-user failures report "User {name} does not have access to this subscription" and "Subscription not found for user {name}" — though `CustomerUser` has no `name` attribute, so those messages currently render with a blank name. Use `first_name` and `last_name`.

**Side effects worth surfacing.** Saving a customer user triggers real external work: creating one queues a CMS sync and, depending on which access flags are on, welcome and per-product login emails. Updating always re-syncs the CMS, newly enabled flags send more emails, and **turning `console_access` off suspends the customer's service**. Deleting re-syncs. None of this is currently signposted in the UI. Turning off console access in particular deserves an explicit warning.

#### Tab "User Customers"

Links admin users to customers. Endpoints `/user-customers`, filtered by `customer_id`. Fields are just `user_id` and `customer_id`, with soft-delete support including restore and force delete. Small enough to render as a simple list of linked admin users with an add control and a remove action.

**Quirk.** No uniqueness rule on the `user_id` plus `customer_id` pair, so duplicate links are creatable. Add one.

### 5.2 Under a Customer Subscription

Three tabs on the subscription detail and edit pages.

#### Tab "Site deployment jobs" — read only

Endpoint `GET /customer-subscriptions/{id}/deployment-jobs`. No create, edit, or delete. Ordered newest batch first, then by step position ascending.

| # | Field | Label | Type | Search | Sort | Toggle |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `batch_id` | Batch | text, **copyable** | yes | no | yes, visible |
| 2 | `position` | Step | text, centred | no | yes | no |
| 3 | `job_name` | Job | text, title-cased | yes | yes | no |
| 4 | `status` | Status | **badge** | no | yes | no |
| 5 | `error_message` | Error | text, wrapped, truncated at 200 | no | no | hidden |
| 6 | `parameters` | Parameters | JSON, truncated at 97 | no | no | hidden |
| 7 | `started_at` | Started at | dateTime | no | yes | yes, visible |
| 8 | `finished_at` | Finished at | dateTime | no | yes | yes, visible |
| 9 | `created_at` | Record created | dateTime | no | yes | hidden |

Status badge colours: `pending` neutral, `running` amber, `completed` green, `failed` red. Badge text is the raw status value.

Copying a batch id shows the toast "Batch id copied".

**Quirk.** There is no polling or auto-refresh, so watching a deployment means manually reloading. Since this table is the only feedback channel for every queued action, polling while any job is `pending` or `running` would be a significant improvement. Errors are also hidden by default despite being the main reason to look at this table — show them when a row has failed.

#### Tab "Manual environment variables"

Endpoint `GET /env-variables?customer_subscription_id={id}`, then narrowed to keys the template marks `requires_manual_fill` for this subscription's type. When the subscription has no type, or the type has no manual keys, the tab is empty.

| # | Field | Label | Type | Search | Detail |
| --- | --- | --- | --- | --- | --- |
| 1 | `key` | Key | text | yes | |
| 2 | `display_label` | Label | computed text | no | The template's `admin_label`, falling back to the raw key |
| 3 | `help_text` | Help | text, truncated at 40 | no | From the template row |
| 4 | `value` | Value | text, truncated at 40 | no | Clear text today |

No create, no delete. **Row action.** Edit, opening a modal with `key` shown read-only and `value` editable (nullable, max 65535), hinted with the template's help text.

This tab is the operator's fill-in-the-blanks screen for a new site, so the label and help text carry real weight. Showing them inline rather than truncated at 40 characters would help.

#### Tab "All environment variables"

Same endpoint, unfiltered. Sorted by `key` ascending — the only table in the app with a default sort.

| # | Field | Label | Type | Search | Sort | Toggle |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | `key` | Key | text | yes | yes | no |
| 2 | `value` | Value | text, truncated at 60 | no | no | no |
| 3 | `updated_at` | Updated | dateTime | no | yes | hidden |

No create, no delete. Same edit modal as the manual tab.

**Quirk.** Both env tabs render secret values in clear text — these are live production credentials. Mask by default with an explicit reveal, and consider logging reveals.

---

## 6. Non-CRUD screens

### 6.1 Subscription action cluster

These live on the subscription edit page and are the operational heart of the app. Each is a header action with its own confirmation copy.

| # | Action | Visible when | Confirmation | Endpoint |
| --- | --- | --- | --- | --- |
| 1 | **Deployment pipeline steps** | Always | None — navigates | Route to `/subscriptions/:id/pipeline` |
| 2 | **Re-create site on Forge** | `forge_site_id` is blank **and** `server_id` is set | Heading "Create site on Forge". Body "This will queue a job to create the site on your Forge server. Ensure the server is correct. Continue?" Submit "Queue create site job" | `POST /customer-subscriptions/{id}/recreate-site` |
| 3 | **Generate App Logos** | Always | Heading "Generate PWA Logos". Body "This will generate PWA icons from the uploaded logo. Continue?" Submit "Generate Logos" | `POST /customer-subscriptions/{id}/generate-logos` |
| 4 | **Deploy Site** | Always | Heading "Deploy Site". Body "This will trigger a site deployment. Continue?" Submit "Deploy" | `POST /customer-subscriptions/{id}/deploy` |
| 5 | **Pull env from server** | `server_id` **and** `forge_site_id` both set | Heading "Pull environment from Forge". Body "This fetches the site .env from Laravel Forge and updates the stored env copy and env variable rows for this subscription. Existing keys will be overwritten with server values (FORGE_API_KEY is skipped). Continue?" Submit "Pull env" | `POST /customer-subscriptions/{id}/pull-env` |
| 6 | **Edit Server Details** | Always | No confirmation, but a form modal with one field: `server_id`, select, label "Forge Server", required, pre-filled | `PUT /customer-subscriptions/{id}/server` |
| 7 | **Delete** | Always | Standard delete confirm. **Permanent** | `DELETE /customer-subscriptions/{id}` |

Action 2 succeeds with the toast "Site creation queued" and body "Batch {uuid} — first step will run on the queue. Check Site deployment jobs below." On failure, "Could not schedule site creation" with the error message.

Action 5 succeeds with "Environment pulled from server" and then reloads the page so the env tabs refresh. On failure, "Could not pull environment" with the error message. It runs synchronously against Forge, so it needs a loading state.

**Quirk.** Actions 3, 4, and 6 fire with **no success toast at all** — an operator clicks Deploy Site and gets no confirmation that anything happened. Add success feedback to all three. Actions 3 and 5 are also synchronous and can take a while.

Filament's "Back to Customer" action is a navigation affordance; use a breadcrumb instead.

### 6.2 Guided create-subscription flow

The primary path for onboarding, reached from the Customer Subscriptions tab on a customer. Substantially richer than the plain form and the only path that schedules a deployment.

**Hidden state** the form maintains: `urlConfirmed` (whether DNS verification passed), `postfix` (the computed domain suffix), and `theType` and `theVertical` (slug parts used to build the database name).

**Section "Customer Info"**

| # | Field | Type | Label | Required | Reactive behaviour |
| --- | --- | --- | --- | --- | --- |
| 1 | `customer_id` | select, searchable, preloaded | Customer | yes | Defaults to the parent customer. On change, auto-fills `app_name` and `url` from the company name and recomputes `database_name` |
| 2 | `subscription_type_id` | select | Subscription Type | yes | Defaults to 1. On change, sets the type slug, rebuilds `postfix`, recomputes `database_name` |
| 3 | `vertical` | select | Vertical | yes | On change, rebuilds `postfix` and recomputes `database_name` |
| 4 | `url` | text with a live suffix | URL | yes | Debounced. Lowercases and trims, resets `urlConfirmed`, recomputes `database_name`, checks uniqueness, then runs a DNS check |
| 5 | `server_id` | select | Server | yes | Options from Forge servers by name; value is the Forge server id |
| 6 | `app_name` | text | App name | yes | Auto-generated, still editable. Hint "Will be auto-generated from customer name", placeholder "e.g., MyCompanyApp" |
| 7 | `database_name` | text | Database name | yes | Auto-derived, still editable |

**Vertical options:** `blackwidow.org.za`, `aims.work`, `aims.world`, `bvigilant.co.za`, `siyaleader.org.za`, `aims.net.za`, mapping to the slugs `blackwidow`, `aims_work`, `aims_world`, `bvigilant`, `siyaleader`, `aims_net_za`.

**Derivation rules to reimplement:**
- `app_name` — strip non-alphanumerics from the company name, TitleCase, no spaces; falls back to `CustomerApp`
- `url` — lowercase the company name, non-alphanumerics become hyphens, collapse repeats, trim, truncate to 50
- `postfix` — `.{type slug}.{vertical domain}`, e.g. `.console.aims.work`
- `database_name` — `{url}_{type slug}_{vertical slug}`, then normalised so spaces, dashes, dots, and special characters become underscores
- `database_user` — the normalised database name truncated to 32 characters (MySQL's limit)

**URL field specifics.** Validation is required, unique across subscriptions, pattern `^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$`, min 3, max 63. Placeholder "e.g., my-company". The hint shows `Full URL: https://{url}{postfix}` once both parts exist, otherwise "Enter a URL slug (will be auto-generated from customer name)". A trailing icon button re-runs the DNS check, showing a check when confirmed and a warning otherwise. A taken URL raises the error toast "This URL is already taken".

**DNS check.** Resolves an A record for the assembled domain. Success shows "Domain Resolves to IP {domain}" and sets `urlConfirmed`; failure shows "Domain does not resolve to IP {domain}". **There is no endpoint for this yet** — see section 9.

**Section "Logos".** Five uploads, nullable, max 10 MB each, with the type-dependent labels from 4.2. No file-type restriction today; restricting to images is recommended.

Filament also renders an "ENV File" section containing a single disabled placeholder for `forge_site_id`. It is a stub with no function on create — drop it.

**On submit:**
1. Assemble `domain` from `url` plus `postfix` and re-run the DNS check. On failure, block with a field error: "The domain does not resolve to a valid IP."
2. Normalise the database name and derive `database_user`.
3. Create the subscription with `url` stored as `https://{domain}`.
4. Schedule the deployment pipeline, which stamps the queue start time, clears previous errors, creates the pipeline rows, and dispatches the first step.
5. Show the success toast: "The customer subscription has been created and the deployment process has started."

Maps to `POST /customer-subscriptions` with `trigger_site_deployment: true`. Note the logo uploads are **not** persisted by this flow in Filament — they are collected and discarded. Either wire them up or remove them from the form.

### 6.3 Plain create-subscription form

The standalone create page at `/subscriptions/new`, for admin and repair work: direct field entry using the form in 4.2, with no DNS check, no suffix building, and **no deployment scheduling**. Use it to fix or backfill a record without side effects.

Maps to `POST /customer-subscriptions` without the deployment flag.

Because these two paths differ in consequence, **each screen must state plainly whether saving will trigger a deployment.** An operator landing on the wrong one should be able to tell immediately.

### 6.4 Deployment pipeline steps page

Route `/subscriptions/:id/pipeline`. Requires the same permission as editing a subscription. Heading "Deployment pipeline steps".

Section heading: "Steps from app:complete-creation". Description: "Each action queues that job alone in a new batch (same job classes as the full `app:complete-creation` pipeline). Check the Site deployment jobs tab on the subscription edit page for status."

The body is a dynamically generated list of step buttons from `GET /customer-subscriptions/{id}/pipeline-steps`.

**The step list is not fixed.** It is composed of a prefix, a common middle, and a conditional tail.

The first two steps exist **only if** the subscription has a `database_name` **and** its subscription type has `project_type` of `php`:

| Index | Job | Label |
| --- | --- | --- |
| 0 | `provision_forge_server_database` | 0. Create Forge server database |
| 1 | `create_forge_server_database_user` | 1. Create Forge database user |
| 2 | `create_site` | 2. Create Site |

For everything else the sequence starts at index 0 with Create Site, and **every subsequent index shifts down by two.** Indices below assume the PHP-with-database case.

| Index | Job | Label |
| --- | --- | --- |
| 3 | `ensure_forge_site` | 3. Ensure Forge Site |
| 4 | `sync_forge` | 4. Sync Forge |
| 5 | `add_git_repo` | 5. Add Git Repo |
| 6 | `add_env` | 6. Add Env |
| 7 | `add_deployment_script` | 7. Add Deployment Script |
| 8 | `add_ssl` | 8. Add Ssl |
| 9 | `deploy_site` | 9. Deploy Site |

Six further steps append **only when** `subscription_type_id` is 1, 2, 9, 10, or 11:

| Index | Job | Label |
| --- | --- | --- |
| 10 | `send_forge_command` | 10. Forge command: php artisan key:generate --force |
| 11 | `send_forge_command` | 11. Forge command: php artisan migrate --force |
| 12 | `send_forge_command` | 12. Forge command: php artisan db:seed BaseLineSeeder --force |
| 13 | `deploy_site` | 13. Deploy Site |
| 14 | `send_system_config` | 14. Send system config |
| 15 | `send_forge_command` | 15. Forge command: php artisan storage:link |

Labels are always prefixed `{index}. `. Special cases are spelled out above; anything else is the job name converted from snake_case to Title Case.

Because indices shift, **always render from the endpoint response rather than a hardcoded list.**

**Each step button:** confirms with heading "Queue this step?" and body "Queues a new batch with only: {label}" truncated at 120 characters. On confirm it calls `POST /customer-subscriptions/{id}/pipeline-steps/{index}`, which creates a single job row in a fresh batch and dispatches it immediately. Success toast "Step queued" with body "Batch: {uuid}". An invalid index gives "Invalid step"; other failures give "Could not queue step" with the message.

Queueing a single step deliberately does **not** touch the deployment queue timestamp, so steps can be re-run freely without tripping the "already scheduled" guard.

This page shows **no status at all** — results appear only in the Site deployment jobs tab on another page. Showing per-step status inline, from the deployment jobs endpoint, would make this dramatically more usable: an operator currently has to hop between two screens to run a pipeline and watch it.

---

## 7. Endpoint mapping

All paths are prefixed `/api/backend`. Lists return a Laravel paginator; single records return `{ data: ... }`; deletes return `{ ok: true, id: N }`.

### Auth

| UI | Method | Path |
| --- | --- | --- |
| Login | `POST` | `/login` |
| Logout | `POST` | `/logout` |
| Current user, roles, permissions | `GET` | `/user` |

### Customers

| UI | Method | Path |
| --- | --- | --- |
| List | `GET` | `/customers` (`per_page`, `page`, `trashed=with\|only`) |
| Detail | `GET` | `/customers/{id}` |
| Create | `POST` | `/customers` |
| Save | `PUT` | `/customers/{id}` |
| Delete | `DELETE` | `/customers/{id}` |
| Restore | `POST` | `/customers/{id}/restore` |
| Force delete | `DELETE` | `/customers/{id}/force` |

### Customer subscriptions

| UI | Method | Path |
| --- | --- | --- |
| List | `GET` | `/customer-subscriptions` (`customer_id`, `subscription_type_id`) |
| Detail | `GET` | `/customer-subscriptions/{id}` (`include_env`) |
| Create, guided | `POST` | `/customer-subscriptions` with `trigger_site_deployment: true` |
| Create, plain | `POST` | `/customer-subscriptions` |
| Save, panic toggle | `PUT` | `/customer-subscriptions/{id}` |
| Delete | `DELETE` | `/customer-subscriptions/{id}` |
| Re-create site | `POST` | `/customer-subscriptions/{id}/recreate-site` |
| Generate logos | `POST` | `/customer-subscriptions/{id}/generate-logos` |
| Deploy | `POST` | `/customer-subscriptions/{id}/deploy` |
| Pull env | `POST` | `/customer-subscriptions/{id}/pull-env` |
| Edit server | `PUT` | `/customer-subscriptions/{id}/server` |
| Pipeline step list | `GET` | `/customer-subscriptions/{id}/pipeline-steps` |
| Queue one step | `POST` | `/customer-subscriptions/{id}/pipeline-steps/{index}` |
| Deployment jobs tab | `GET` | `/customer-subscriptions/{id}/deployment-jobs` |

### Customer users

| UI | Method | Path |
| --- | --- | --- |
| List, tab | `GET` | `/customer-users` (`customer_id`, `trashed`) |
| Detail | `GET` | `/customer-users/{id}` |
| Create | `POST` | `/customer-users` |
| Save | `PUT` | `/customer-users/{id}` |
| Delete | `DELETE` | `/customer-users/{id}` |
| Restore | `POST` | `/customer-users/{id}/restore` |
| Force delete | `DELETE` | `/customer-users/{id}/force` |
| Update password | `POST` | `/customer-users/{id}/update-password` |
| Send welcome email | `POST` | `/customer-users/{id}/send-welcome-email` |
| Send login email | `POST` | `/customer-users/{id}/send-login-email` |
| Manage access rights | `PUT` | `/customer-users/{id}/access-rights` |

Bulk send-login-email has no bulk endpoint; loop the single endpoint and aggregate results.

### Remaining resources

Every one follows the same five-route shape — `GET` list, `POST` create, `GET` one, `PUT` update, `DELETE` destroy:

| Resource | Base path | Extras |
| --- | --- | --- |
| Users | `/users` | Roles synced via the `roles` field |
| User customers | `/user-customers` | `restore`, `force` |
| Subscription types | `/subscription-types` | `restore`, `force` |
| Deployment scripts | `/deployment-scripts` | |
| Deployment templates | `/deployment-templates` | |
| Env variables | `/env-variables` | |
| Template env variables | `/template-env-variables` | |
| Forge servers | `/forge-servers` | `POST /forge-servers/sync` |
| Nginx templates | `/nginx-templates` | |

### Filament actions with no endpoint

Deliberately dropped as navigation-only: `backToCustomer`, `navigate`, `backToEdit`. Use routing and breadcrumbs.

---

## 8. Quirk register

Every item below is current Filament behaviour that looks wrong. Each is an independent decision.

### Would carry real risk if reproduced

| # | Quirk | Recommendation |
| --- | --- | --- |
| 1 | `password` is required on edit for both Users and Customer Users, with no create-only guard, so every edit demands a new password | Optional on edit; ignore when blank |
| 2 | Env variable values and the customer `token` render in clear text; these are live production credentials | Mask by default with explicit reveal |
| 3 | `syncFromForge` has no authorization check | Gate on `Create:ForgeServer` or a dedicated permission |
| 4 | Deploy Site, Generate App Logos, Edit Server Details, and Send Welcome Email give no success feedback | Add success toasts to all four |
| 5 | Turning off `console_access` silently suspends the customer's service | Warn explicitly before saving |
| 6 | No unique validation on `User.email`, `CustomerUser.email_address` at resource level, or the `user_id` plus `customer_id` pair | Add unique rules ignoring the current record |
| 7 | `driver_access` is missing from the customer-user form but honoured elsewhere | Add the toggle |

### Broken or misleading

| # | Quirk | Recommendation |
| --- | --- | --- |
| 8 | The "Product" filter applies `where(subscription_type_id, null)` when cleared, returning zero rows | Omit the parameter when blank |
| 9 | `CustomerResource::$recordTitleAttribute = 'name'`, but the column is `company_name`, so modal headings render blank | Use `company_name` |
| 10 | The subscription tab's Edit action renders the **Customer** form, because the relation manager defines no form | Give it a subscription form or drop it for Navigate |
| 11 | Bulk email toasts interpolate `$user->name`, which does not exist on `CustomerUser`, so the name renders blank | Use `first_name` and `last_name` |
| 12 | `CustomerSubscriptionInfolist` is dead code; the detail page falls back to a disabled form | Build a real detail page, using that infolist as the reference |
| 13 | `RequiredEnvVariables` uses four different names across class, model, URL, and nav label | Standardise on Template Env Variables |
| 14 | Logo uploads in the guided create flow are collected but never persisted | Wire them up or remove them |
| 15 | Ambiguous auto-generated headers: `Id` for `customer.id`, `Name` for `user.name` | Label them explicitly and show a human-readable value |

### Usability

| # | Quirk | Recommendation |
| --- | --- | --- |
| 16 | Every `script` field is a `longText` shell script or nginx config in a small plain textarea | Full-width code editor, monospace, syntax highlighting |
| 17 | `customer_subscription_id` is a raw integer input on Deployment Scripts and Env Variables | Searchable select |
| 18 | Env Variables and Forge Servers have no per-row delete, only bulk | Add a row delete action |
| 19 | Deployment jobs never auto-refresh, and `error_message` is hidden by default | Poll while any job is pending or running; surface errors on failed rows |
| 20 | The pipeline page shows no status, so running a pipeline means switching screens | Show per-step status inline |
| 21 | Six raw deployment timestamps stand in for a progress indicator | One progress or status component |
| 22 | The subscription list has 23 columns and scrolls horizontally | Trim to essentials, keep the rest toggleable |
| 23 | Logo columns print raw storage paths | Render thumbnails |
| 24 | `project_type` is free text but behaviour keys off the exact value `php` | Constrain to a select |
| 25 | `subscription_type_id` is not reactive on the subscription form, so logo labels go stale until reload | Make labels live |
| 26 | `syncFromForge` runs synchronously, one HTTP call per server, and never removes stale rows | Queue it; consider reporting servers that vanished |
| 27 | No unique rule or index on `forge_server_id`, though the sync matches on it | Add a unique constraint |
| 28 | All resources share one sidebar icon | Assign distinct icons |
| 29 | Logo slots 4 and 5 are labelled "Not Used" for every type | Hide them |
| 30 | Empty values use `-` in some places and `—` in others | Pick one |

---

## 9. Gaps to close before or during the build

These block specific screens. Each needs a backend change.

| # | Gap | Blocks | Suggested fix |
| --- | --- | --- | --- |
| 0a | **No endpoint accepts a `search` parameter.** Every Filament table has a search box; none can be reproduced | Search on all 12 list screens | Add `search` to each list endpoint, matching the columns marked searchable in section 4 |
| 0b | **No endpoint accepts sort parameters.** Lists return a fixed order; many Filament columns are sortable | Sortable column headers everywhere | Add `sort` and `direction`, validated against an allowlist per resource |
| 1 | **Customer secrets are hidden on every read.** `token`, `google_api_key`, and all five `s3_*` fields are stripped unconditionally, so an edit form cannot show current values and saving risks blanking them | The S3 / MinIO section of the customer form — a real feature today | Add an `include_secrets` flag gated on a permission, or a dedicated credentials endpoint |
| 2 | **No DNS verification endpoint** | The guided create flow's live URL check and its blocking pre-save validation | `POST /customer-subscriptions/verify-domain` taking a domain and returning whether it resolves |
| 3 | **No roles list endpoint** | The Roles multi-select on the user form | `GET /roles` returning id and name, gated on `ViewAny:Role` |
| 4 | **No password reset endpoints** | The "Forgot password?" link | Add request and reset endpoints, or drop the link |
| 5 | **No global search endpoint** | Topbar search | Add a cross-resource search endpoint applying per-resource `ViewAny` checks, or scope search to per-list filtering in v1 |
| 6 | **No URL uniqueness check endpoint** | Inline feedback on the subscription URL field | Reuse the subscriptions list with a `url` filter, or fold it into the verify-domain endpoint |
| 7 | **No bulk endpoints** | Bulk delete and bulk send-login-email | Loop single endpoints and aggregate, or add bulk routes if volume warrants |
| 8 | **`Customer.uuid` is not mass-assignable** | The `uuid` field on the customer form | Add to `$fillable`, or drop the field since it is system-generated |
| 9 | **Pagination default mismatch** — Filament shows 10 per page, the API defaults to 25 | Nothing, but it will look inconsistent | Pick one and set it explicitly on every request |
| 10 | **No dashboard data endpoint** | Any real dashboard | Decide the dashboard's content first; recent job failures and stalled pipelines are derivable from existing endpoints |

---

## Appendix: source reference

| Area | Location |
| --- | --- |
| Filament resources | `app/Filament/Resources/{Resource}/` — resource class, `Pages/`, `Tables/`, `Schemas/`, `RelationManagers/` |
| Panel configuration | [`app/Providers/Filament/AdminPanelProvider.php`](../app/Providers/Filament/AdminPanelProvider.php) |
| Policies | `app/Policies/` — 13 policies, all Shield-style |
| Shield config | `config/filament-shield.php` |
| Backend API routes | [`routes/backend-api.php`](../routes/backend-api.php) |
| Backend API controllers | `app/Http/Controllers/Api/Backend/` |
| API contract | [`BACKEND_API.md`](BACKEND_API.md) |
| Deployment pipeline | `app/Services/SiteDeploymentScheduler.php` |
| Forge integration | `app/Services/ForgeService.php`, `app/Services/ForgeServerSyncService.php`, `app/Helpers/ForgeApi.php` |
| Logo generation | `app/Services/CustomerSubscriptionService.php`, `app/Helpers/ImageHelper.php` |
| Customer user side effects | `app/Models/CustomerUser.php` boot hooks |
