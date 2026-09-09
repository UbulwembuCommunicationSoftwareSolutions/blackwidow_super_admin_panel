# Handoff: Reseller Admin Panel (Filament → Vue rebuild)

## Overview

A custom admin panel for a software reseller managing client companies and the software
instances deployed for them. It replaces a Filament panel at `/admin` with a Vue SPA
against the Sanctum-authenticated JSON API at `/api/backend`.

The design covers the six screens the operators actually live in:

1. Operations overview (dashboard)
2. Customers list
3. Customer detail, with nested tabs
4. Customer subscription cockpit — the operational heart of the app
5. Guided create-subscription flow
6. Deployment pipeline steps
7. Configuration screens (template env vars, subscription types, deployment scripts, Forge servers, admin users)

Four roles use it daily: ops engineers running deployments, support agents managing
client users, account managers onboarding clients, and super admins doing config.

## About the design files

`Reseller Admin.dc.html` in this bundle is a **design reference created in HTML** — a
prototype showing intended look and behaviour, not production code to copy. The task is
to **recreate it in the target codebase's environment** (Vite + Vue 3 SPA, `vue-router`,
Pinia, Tailwind, headless primitives) using that project's established patterns.

Open it in a browser. Navigation, tabs, theme switching, and secret reveal all work; the
data is static mock data defined in the file's logic class.

The authoritative behavioural contract is the separate **Frontend Rebuild Spec** (pages,
routes, columns, fields, validation, actions, modal copy, permission gating) and
`BACKEND_API.md`. This README covers **visual and interaction design**. Where the two
disagree on structure, the spec wins; where they disagree on appearance, this wins.

## Fidelity

**High fidelity.** Final colours, typography, spacing, densities, and interaction
states. Recreate the UI closely using the codebase's own component primitives.

Deliberately **not** covered here (still open design work): the plain create-subscription
form, standalone subscription list page, login screen, and modal shells. Each follows the
patterns documented below.

---

## Design tokens

Two themes. Values are set as CSS custom properties on a host element; the light set is
authoritative for print/light, dark is the product default.

### Colour

| Token | Dark | Light | Use |
| --- | --- | --- | --- |
| `--bg` | `#0f1115` | `#f5f6f8` | Page background, input fills, code blocks |
| `--panel` | `#16181d` | `#ffffff` | Cards, tables, sidebar, topbar |
| `--panel2` | `#1c1f26` | `#f7f8fa` | Table header rows, inset chips |
| `--hover` | `#1c1f26` | `#f2f4f7` | Row and button hover |
| `--line` | `#262a33` | `#e2e5ea` | All standard borders and row dividers |
| `--lineSoft` | `#1e222a` | `#eef0f4` | Dividers inside cards (key/value lists) |
| `--line2` | `#3a3f4b` | `#cfd4dd` | Input borders, checkbox outlines, inactive step |
| `--ink` | `#e9eaee` | `#15171c` | Primary text |
| `--ink2` | `#a0a6b4` | `#5b6270` | Secondary text, labels |
| `--ink3` | `#6e7481` | `#8a91a0` | Tertiary text, timestamps, meta |
| `--acc` | `#5c81ff` | `#2f5bff` | Accent: primary buttons, links, active nav, running state |
| `--accBg` | `#1a2547` | `#eaefff` | Accent surface (active nav, info banner, badges) |
| `--accLine` | `#2c3d78` | `#c9d6ff` | Accent border |
| `--accInk` | `#a8bcff` | `#26408f` | Text on accent surface |
| `--ok` | `#34c98a` | `#0f9d63` | Completed, resolved, configured |
| `--okBg` | `#0f2f22` | `#e6f6ee` | Success badge surface |
| `--warn` | `#f5a524` | `#b06f00` | Awaiting action, stale, incomplete |
| `--warnBg` | `#2e2208` | `#fdf3e0` | Warning banner/badge surface |
| `--warnLine` | `#5c4310` | `#f0dcb4` | Warning border |
| `--warnInk` | `#f5c97a` | `#7a4d00` | Text on warning surface |
| `--err` | `#e5484d` | `#d0323a` | Failed, destructive, required |
| `--errBg` | `#3a1418` | `#fdecec` | Error surface (inline job errors) |
| `--errLine` | `#5c1f24` | `#f4cdcf` | Error border |

Set `color-scheme: dark` / `light` on the host so native scrollbars and form controls follow.

The accent is themeable — treat `--acc` as a single swap point. Alternates that were
tested and work: `#34c98a`, `#f5a524`, `#a06cf5`.

### Typography

- UI: **Geist**, weights 400 / 500 / 600. Intermediate weights used as `450`, `500`, `550`.
- Mono: **Geist Mono**, 400 / 500. Used for every machine value: URLs, domains, keys,
  env values, IPs, ids, timestamps, counts, batch ids, shell scripts, permission names.
  This is the single strongest signal in the design — anything the machine owns is mono,
  anything a human wrote is Geist.

Both from Google Fonts.

| Role | Size | Weight | Notes |
| --- | --- | --- | --- |
| Page heading (`h1`) | 19px | 600 | `letter-spacing: -0.02em` |
| Page subhead | 12px | 400 | `--ink2` |
| Card heading | 12.5px | 600 | |
| Table column header | 10px | 500 | uppercase, `letter-spacing: 0.07em`, `--ink3` |
| Table cell | 12–12.5px | 400/500 | mono cells 11.5px |
| Stat number | 26px | 600 | mono, `letter-spacing: -0.03em` |
| Body / helper text | 11–11.5px | 400 | `--ink3`, `line-height: 1.45–1.5` |
| Badge | 10.5px | 550 | mono |
| Nav item | 12.5px | 450 inactive / 600 active | |
| Field label | 11.5px | 550 | `--ink2` |
| Button | 12–12.5px | 550 | |

Base body size 13px. Nothing below 10px.

### Spacing, radius, elevation

- Page padding `22px`, bottom `40px`. Section gap `14–18px`.
- Card padding `13–16px`. Table cell padding `0 14px`.
- Radius: cards `10px`, buttons and inputs `7px`, badges and chips `4–5px`, small
  inline controls `6px`, avatars `50%`.
- **No shadows anywhere.** Depth comes entirely from 1px `--line` borders and the
  `--bg` / `--panel` / `--panel2` stack. Keep it that way.
- Borders are always exactly `1px solid var(--line)`; inputs use `--line2`.

### Density

`--rowh` drives table row height: `34px` dense (default), `40px` comfortable. Rows that
contain a control or a mini-stepper are a fixed `38px` regardless.

---

## App shell

**Sidebar** — 224px fixed, `position: sticky; top: 0; height: 100vh`, `--panel`
background, right border. Contents top to bottom:

- Brand block, 16px padding: 24px `--acc` rounded-6px square with a mono capital, then
  the console name (12.5px/600) over the reseller's root domain (10.5px mono, `--ink3`).
- Two labelled groups: **Operations** (Overview, Customers, Subscriptions, Deployments)
  and **Configuration** (Template env vars, Subscription types, Deployment scripts,
  Forge servers, Admin users). Group labels are 10px uppercase, `0.09em` tracking, `--ink3`.
- Nav item: 29px tall, 8px horizontal padding, 7px radius, 9px gap, 15px stroke icon at
  0.85 opacity, label, optional right-aligned mono count chip. Active = `--accBg`
  background, `--acc` text, weight 600. Hover = `--hover` background, `--ink` text.
  **Every item gets a distinct icon** — the Filament panel used one icon for all of them.
- Footer, pushed down with `margin-top: auto`, separated by a top border: 24px circular
  avatar with initials, name over role (mono, `--ink3`), and a 24px square theme toggle
  (sun/moon stroke icon).

**Topbar** — 48px, sticky, `--panel`, bottom border, 16px horizontal padding:
breadcrumb on the left (12px, `--ink3`, with `/` separators at 0.4 opacity; the last
crumb is `--ink` at 550, and any crumb containing a `.` renders mono at 11.5px), then a
260px search affordance on the right — 28px tall, `--bg` fill, `--line` border, 7px
radius, magnifier icon, placeholder "Search customers, URLs, keys", and a mono `/`
keyboard hint chip.

The search endpoint does not exist yet (spec gap 0a/5). Ship the affordance disabled or
scoped to per-list filtering until it does.

**Buttons** — one 30px-tall family, 11–14px horizontal padding, 7px radius, 6px gap,
12px/550, `white-space: nowrap`, `flex: 0 0 auto`:

- Primary: `--acc` fill, `#fff` text, hover `filter: brightness(1.08)`.
- Secondary: transparent, `--line` border, `--ink2` text; hover `--hover` fill, `--ink` text.
- Destructive: transparent, `--errLine` border, `--err` text.
- Busy: prepend a 12px spinner (`@keyframes spin`, 0.9s linear infinite) on a
  quarter-circle stroke path.

Inline table actions are plain text, 11.5px: `--acc` for the interesting one (Reveal,
Queue, Re-run), `--ink3` for the rest, and a `⋯` glyph (13px, `line-height: 1`) in a
26px right-aligned track where a row has an overflow menu.

**Badges** — 10.5px/550 mono, `2px 7px`, 5px radius. Surface/text pairs:
completed `--okBg`/`--ok`, running `--accBg`/`--acc`, failed `--errBg`/`--err`,
pending and skipped `--panel2`/`--ink3`. Badge text is the raw status value, lowercase.

**Banners** — 9px radius, 10–11px padding, 10px gap, 14px stroke icon at the top left,
12px text at `line-height: 1.5`. Warning uses `--warnBg`/`--warnLine`/`--warnInk`,
info uses the accent triad. Lead with a bolded clause where there is a consequence.

**Tables** — CSS grid, not `<table>`. Every table repeats one `grid-template-columns`
string on its header row and its body rows; keeping those in sync matters more than any
other implementation detail here. Rules that must survive:

- Header row 29–30px, `--panel2` fill, bottom border, sticky if the list is long.
- Every header cell and every text cell: `white-space: nowrap; overflow: hidden`, plus
  `text-overflow: ellipsis` where truncation is acceptable.
- Flexible tracks are `minmax(<floor>, <n>fr)` — never bare `1fr`. A bare `1fr` next to
  several fixed tracks collapses the primary identifier column to nothing at narrow widths.
- Right-aligned numeric columns get `padding-right: 12px` so they cannot touch the next cell.
- Row hover `--hover`. Rows that navigate are `cursor: pointer` on the whole row.
- Footer strip, 9px padding, 11.5px `--ink3`: "Showing 1–9 of 9" left, per-page control right.

Pagination: pick one page size and send it explicitly on every request — Filament
defaulted to 10, the API to 25. The design shows **25**.

**Mini stepper** (in table rows and cards) — six 4px-tall, 2px-radius flex-1 bars with
3px gaps, coloured `--ok` / `--err` / `--acc` / `--line2`; the running bar animates
`breathe 1.6s ease-in-out infinite` (opacity 1 → 0.35 → 1). Each bar carries the stage
name as its `title`. Followed by a mono 10.5px stage label — `live`, `failed`, or `4 of 6`.

---

## Screen 1 — Operations overview

**Purpose.** Answer "what is broken right now?" in one screen. There is no dashboard
endpoint; everything here derives from existing list endpoints (spec gap 10).

**Layout.** Header row (heading + subhead left, "Last 7 days" secondary and "New
subscription" primary right). Then a stat strip:
`grid-template-columns: repeat(auto-fit, minmax(180px, 1fr))`, 12px gap. Then a two-panel
row: `repeat(auto-fit, minmax(360px, 1fr))`, 14px gap, `align-items: start`.

**Stat card.** 10px radius, `--panel`, 13/14px padding. 11px/500 label, then the value
(26px mono 600) baseline-aligned with an 11px `--ink3` delta. Value colour is `--ink`
except where the number is itself a problem: failed jobs `--err`, awaiting manual env
`--warn`. Four cards: Active subscriptions, Deployments today, Failed jobs, Awaiting
manual env.

**Failed deployment jobs** (wider panel). Card header: 6px `--err` dot, "Failed
deployment jobs", right-aligned mono meta "live · polling 5s". Grid
`1.4fr 1fr 88px 74px` — Site (mono 11.5px), Job, Status badge, Started (mono, right).
Each row is followed by its error message: mono 11px `--err` on `--errBg` with an
`--errLine` border, 6px radius, `6px 9px` padding, `line-height: 1.45`, `text-wrap: pretty`.

Errors are shown inline by default. In Filament `error_message` was a hidden column,
which is the main reason anyone opens this table.

**Incomplete pipelines** (narrower panel). Card header with a 6px `--warn` dot. Each
entry is a 12/14px padded block: mono URL and a right-aligned mono "4 of 6", then the
mini stepper, then an 11px `--ink2` note explaining the stall ("Stopped at Add Git Repo,
2 hours ago", "Waiting on three manual env values"). Whole block navigates to the cockpit.

---

## Screen 2 — Customers list

Heading "Customers" with a mono result count beside it, then filter ("Without deleted
records"), "Columns", and a primary "New customer".

Grid: `26px minmax(170px,2fr) 108px 54px 54px 96px 26px`

| Track | Column | Detail |
| --- | --- | --- |
| 26px | Checkbox | 13px square, 3px radius, `--line2` border |
| `minmax(170px,2fr)` | Company name | 500 weight; a mono `deleted` chip follows the name on trashed rows |
| 108px | Credentials | Two dot+label pairs: 5px dot + mono 10.5px "API" and "S3", 9px gap. Green `--ok` when set, `--line2` when the Google key is absent, `--warn` when S3 is partially filled. `title` carries the full sentence |
| 54px | Sites | mono, right |
| 54px | Users | mono, right, `--ink2`, `padding-right: 12px` |
| 96px | Created | mono 11px, `--ink3` |
| 26px | `⋯` | overflow menu |

The Filament table had 19 columns including the raw API token in clear text. Sites and
Users are derived counts, not spec columns — they are what makes the row worth scanning.
The two boolean icon columns are collapsed into one Credentials cell; `s3_configured` is
virtual and true only when all four of endpoint, key, secret, and bucket are filled.

Row click → customer detail. `⋯` → View, Edit, Delete. Bulk: Delete, Force delete, Restore.
Trashed filter maps to `trashed=with|only`; omit the parameter entirely when unset.

Empty state: heading "No customers", body "Create a customer to get started."

---

## Screen 3 — Customer detail

Header: company name as `h1`, then a mono meta line — `uuid 9f2c…a41e / 4 sites /
max_users 25` with `/` separators at 0.4 opacity. "Edit customer" secondary button right.

**Config-push warning banner**, directly under the header, warning variant:

> Editing level descriptions, in-use flags, or the docket and task labels pushes config
> to this customer's live sites on save. Four sites will be updated.

This is a real external side effect (`SendSystemConfigJob`) that fires silently today.
Show the site count. Confirm before saving when any of those fields changed.

**Two cards**, `repeat(auto-fit, minmax(360px, 1fr))`:

*Terminology* — key/value rows, 4px vertical padding, `--lineSoft` divider, label `--ink2`
left and value 500 right. The three levels that have in-use flags carry a mono 10px
`in use` / `off` suffix (`--ok` / `--ink3`). Covers docket, task, and levels one to five.

*Credentials* — same row rhythm, plus a mono 10px "reveals are logged" chip in the
header. Each row: label, right-aligned mono value, then a `--acc` "Reveal" / "Hide"
text action. Masked as 14 bullet characters; `—` when empty, in `--ink3`, with no action.
Footer note: "Values load from the credentials endpoint on reveal, so a save never blanks
a field it could not read."

That note describes a **blocking backend requirement** (spec gap 1): the API strips
`token`, `google_api_key`, and all five `s3_*` fields on every read, so an edit form
cannot show current values and a naive save risks blanking them. The S3 section of the
customer form cannot ship until an `include_secrets` flag or a credentials endpoint exists.

**Tabs card.** Tab strip: 8/10px top padding, `-1px` bottom margin over the card border.
Tab = `7px 11px 9px` padding, 12.5px; active is 600 `--ink` with a 2px `--acc` bottom
border, inactive 500 `--ink3` with a transparent one. Each tab shows a mono count at 10px
/0.6 opacity. A contextual primary button sits at the right of the strip and changes label
per tab.

*Subscriptions* — `1.5fr 110px 1fr 96px 72px 96px`, 38px rows: mono `--acc` URL
(navigates), type, mini stepper + stage label, version, panic toggle, actions.
Panic toggle: 30×17px pill, 9px radius, 2px padding, 13px white knob, `--acc` when on
and `--line2` when off, `opacity: 0.4` and a `title` explaining why when the product type
is not app-type (ids 3–7). It writes immediately on click.

Filament's Edit action here rendered the **Customer** form by mistake. Drop it in favour
of navigating to the subscription.

*Client users* — `1.3fr 1fr 130px 1fr 108px`, 38px rows: mono email, name with a mono
`super` chip on `--accBg` for system admins, mono phone, then access flags as mono 10px
outlined chips (`--line2` border, `--ink2`), then actions. Nine access toggles plus
`driver_access`, which is missing from the Filament form but honoured by the login logic.

Footer note under the table, 11.5px `--ink3`:

> Saving a client user syncs the CMS and may send welcome or per-product login emails.
> Turning off `console_access` suspends the customer's service and asks for confirmation first.

`console_access` in mono `--warn`. That suspension is silent today and needs an explicit
confirm step.

*Admin access* — no table; wrapping row of 8px-gap chips. Each: `--panel2` fill, `--line`
border, 8px radius, `7px 9px 7px 8px` padding, 22px avatar with initials, name over mono
email, then an 18px × close button that turns `--err` on `--errBg` on hover. Duplicate
`user_id` + `customer_id` pairs must be rejected.

---

## Screen 4 — Subscription cockpit

The operational heart. Header: the full domain as `h1` **in mono** (this screen is about
a machine, not a company), then a mono meta line of product / forge site id / server /
version. Right side is the action cluster, wrapping, right-justified, 7px gap:

| Action | Style | Visible when | Confirmation |
| --- | --- | --- | --- |
| Pipeline steps | secondary | always | none, navigates |
| Pull env from server | secondary, **busy spinner** | `server_id` and `forge_site_id` both set | "Pull environment from Forge" — overwrites stored keys with server values, `FORGE_API_KEY` skipped. Submit "Pull env" |
| Generate logos | secondary | always | "Generate PWA Logos". Submit "Generate Logos" |
| Server details | secondary | always | form modal, one required pre-filled Forge Server select |
| Deploy site | **primary** | always | "Deploy Site". Submit "Deploy" |
| Delete | destructive | always | standard confirm — **permanent**, no soft delete |
| Re-create site on Forge | secondary | `forge_site_id` blank **and** `server_id` set | "Create site on Forge". Submit "Queue create site job" |

Deploy, Generate logos, Server details, and Send welcome email give **no success feedback**
today. All four need success toasts. Pull env and Generate logos are synchronous against
Forge and need the busy state.

**Deployment progress card.** Card header: "Deployment progress", a status badge
("step 5 failed"), and right-aligned mono "batch 7c1e…9b04 · polling".

The stepper is `grid-template-columns: repeat(6, 1fr)`, each cell a 9px-gap column:

- Row one: an 18px circle (1.5px border in the state colour) followed by a flex-1
  1.5px connector bar. Done = filled `--ok` with a white `✓`; failed = filled `--err`
  with a white `!`; pending = transparent fill, `--line2` border, `--ink3` step number.
  Connector is `--ok` up to the last completed step, `--line2` after.
- Row two: stage name (12px/550, `--ink3` when pending) over a mono 10.5px timestamp —
  the real `M j, H:i` value, "failed 14:05", or "not started".

The six stages are the six raw timestamps Filament printed as separate columns:
`site_created_at`, `github_sent_at`, `env_sent_at`, `deployment_script_sent_at`,
`ssl_deployed_at`, `deployed_at`. This single component is the biggest readability win
in the rebuild.

**Three tabs** (same tab strip pattern):

*Deployment jobs* — `44px 1.4fr 92px 1fr 94px 94px`: step position (mono `--ink3`),
job name title-cased, status badge, mono detail, started, finished. Failed rows are
followed by the inline error block described in screen 1, indented to 58px so it aligns
under the job name. **Poll every 5 seconds while any job is pending or running**, and
stop when none are. Batch id is copyable with a "Batch id copied" toast.

*Manual env* — not a table. An intro line, then one 8px-radius card per key on `--panel2`:
label (12.5px/600) + mono key + right-aligned state badge (`filled` green / `awaiting
value` red); help text from the template row at 11.5px `--ink2`, shown in full rather
than truncated at 40 characters; then a row of a 29px value field (mono, `--bg` fill,
border `--line2` when filled and `--errLine` when not), a Reveal/Hide button, and Save.

This is the operator's fill-in-the-blanks screen for a new site, so the label and help
text carry real weight. Keys come from template rows with `requires_manual_fill` on for
this subscription's type; the tab is empty when the type has none.

*All env* — `1fr 1.6fr 96px 86px`, dense rows: mono key (500), mono value, mono updated
time, then Reveal/Hide plus Edit. Sorted by `key` ascending. Secret keys mask to 16
bullets in `--ink3`; revealing switches to `--ink`. Non-secret values sit at `--ink2`
unmasked. Add a per-row delete — Filament only had bulk.

These are live production credentials. Mask by default, reveal explicitly, and log reveals.

---

## Screen 5 — Guided create-subscription flow

`max-width: 1080px`. Heading "New subscription" over "Guided onboarding for {customer}".

**Info banner** (accent variant, up-arrow icon):

> **Saving starts a deployment.** This flow creates the site on Forge and dispatches the
> full pipeline. To backfill or repair a record without side effects, use the plain create
> form instead.

Two paths create subscriptions and they differ in consequence. Every create screen must
state plainly whether saving triggers a deployment.

**Layout.** Two columns (`repeat(auto-fit, minmax(380px, 1fr))`), then a full-width logos
card, then the footer buttons.

*Customer & product card* — five fields, 13px gap. Field = label row (11.5px/550 `--ink2`,
plus a red 11px "required" tag where applicable), then a 31px control with `--line2`
border, 7px radius, `--bg` fill, 10px padding, and a 12px chevron for selects. Optional
11px `--ink3` hint below. Fields: Customer (searchable, preloaded), Subscription type
(mono value; hint "PHP project. Database provisioning steps will be added to the
pipeline."), Vertical, Forge server (label shows name and IP; **value is Forge's server
id, not the local primary key**), App name (mono; hint "Auto-generated from the company
name. Editable.").

*Address card* — the URL slug field is the centrepiece: a single 31px bordered row
containing an editable mono input, a static mono `--ink3` suffix (`.console.aims.work`),
and a 31px square trailing button that re-runs the DNS check. The whole border turns
`--ok` when confirmed; the button shows a green check on `--okBg`, a `--warn` triangle
otherwise. Below it, an 11px status line: `--ok` 550 "Domain resolves" then mono `--ink3`
"A 41.76.109.22 · slug available". Then a read-only Full URL block: mono 12.5px on
`--panel2`, `--line` border, 7px radius, `word-break: break-all`.

Validation: required, unique, `^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]$`, min 3, max 63.
Debounce, lowercase and trim, reset the confirmed flag, recompute the derived values,
check uniqueness, then run DNS. A taken slug raises "This URL is already taken".
On submit, re-run the DNS check and block with the field error "The domain does not
resolve to a valid IP." Neither the DNS check nor the uniqueness check has an endpoint
yet (spec gaps 2 and 6).

*Derived values card* — a mono key/value list with an "editable" chip in the header:
`domain`, `database_name`, `database_user`, `postfix`. Derivations:

- `app_name` — strip non-alphanumerics from the company name, TitleCase, no spaces; fall back to `CustomerApp`
- `url` — lowercase the company name, non-alphanumerics to hyphens, collapse repeats, trim, truncate to 50
- `postfix` — `.{type slug}.{vertical domain}`
- `database_name` — `{url}_{type slug}_{vertical slug}`, then spaces, dashes, dots, and specials to underscores
- `database_user` — the normalised database name truncated to 32 characters (MySQL limit)

Showing these instead of hiding them is the point: the operator can see what the site
will be called before committing.

*Logos card* — `repeat(3, minmax(0,1fr))`, 12px gap. Each slot: label, then a 92px
dashed `--line2` drop zone on `--bg`, 8px radius, centred 11.5px `--ink2` state over mono
10.5px `--ink3` meta; border turns `--acc` on hover. Only three slots — Filament had five,
two of which were labelled "Not Used" for every product type. **Labels are live on the
selected subscription type**: type 3 (responder) reads App Logo / Home Logo / Login Logo,
everything else Login Logo / Menu Logo / Login Background. Restrict to image types.

Note the guided flow in Filament collected logo uploads and discarded them. Wire them up
or leave them out.

**Footer.** "Create & deploy" primary, "Create without deploying" secondary, "Cancel" as
plain `--ink3` text.

---

## Screen 6 — Deployment pipeline steps

`max-width: 960px`. Heading, then a 640px-max explanation at `line-height: 1.5` naming
`app:complete-creation` in mono. A "Re-run failed onward" secondary button sits right.

Card header: mono "console · php · database_name set" left, "16 steps, rendered from the
endpoint" right.

Grid `34px 1fr 92px 104px 84px`: index (mono 11px `--ink3`), then a 5px state dot + label
(mono when the label is a shell command, `--ink2` when pending), status badge, mono
timestamp, and a `--acc` text action — "Re-run" on failed steps, "Queue" otherwise.

Footer note: "Queueing a single step does not touch the deployment queue timestamp, so
steps can be re-run freely."

**The step list is not fixed** and indices shift. Steps 0 and 1 (create database, create
database user) exist only when the subscription has a `database_name` **and** its type has
`project_type` of `php`; without them everything shifts down by two. Six Forge command
steps append only when `subscription_type_id` is 1, 2, 9, 10, or 11. Always render from
`GET /customer-subscriptions/{id}/pipeline-steps`, never a hardcoded list.

Filament's version of this page showed **no status at all** — the operator had to run a
step here and watch it on another screen. Status comes from the deployment jobs endpoint
and belongs inline; poll it the same way.

Each step confirms with "Queue this step?" / "Queues a new batch with only: {label}"
(truncate at 120 chars) and toasts "Step queued" with "Batch: {uuid}".

---

## Screen 7 — Configuration

Five screens sharing one header pattern: `h1`, a one-line subtitle specific to the
resource, and right-aligned header actions. Each has its own breadcrumb
(`Configuration / {resource}`). All tables cap at `max-width: 820–940px` — these are
narrow resources and full-bleed makes them look empty.

*Template env variables* — filter bar with a "Product: console" select and mono
"18 keys · 3 manual". Grid `1fr 1.3fr 1.1fr 74px 86px`: mono key, default value
(manual rows read "left blank per subscription" in `--ink3` rather than showing an empty
cell), admin label, a mono yes/no manual flag in `--warn`/`--ink3`, actions. When the
product filter is cleared, **omit the parameter** — Filament applied
`where(subscription_type_id, null)` and returned zero rows.

*Subscription types* — grid `38px minmax(120px,1fr) minmax(0,1.4fr) 92px 82px 88px 26px`:
id, name (with a mono `app` chip on `--accBg` for app-type products, ids 3–7), mono repo,
mono branch, project type as an outlined mono chip, mono version, `⋯`. Footer note that
`project_type` is a fixed select and `php` adds the database provisioning steps.
It is free text today, and behaviour keys off the exact string.

*Deployment scripts* — the script is the screen. Card header names the subscription and a
mono "sh · 12 lines". Body on `--bg` with `overflow-x: auto`; each line is a
`38px minmax(0,1fr)` grid — right-aligned mono line number at 0.55 opacity and
`user-select: none`, then the code as mono 11.5px `white-space: pre` at `line-height: 1.8`.
Comments and echoed strings tint `--ok`, subshell punctuation `--ink2`. Footer: "Edit
script", "Copy", and a note that the subscription is chosen with a searchable select.
Filament put a `longText` shell script in a small plain textarea and a raw integer input
for the subscription id.

*Forge servers* — grid `70px minmax(0,1fr) 108px 60px 26px`: mono Forge id, name with a
mono `stale` chip (`--warnBg`/`--warn`, title "Present locally but no longer returned by
Forge"), mono IP, site count, `⋯`. Header actions "Sync from Forge" secondary and "New
forge server" primary. Sync confirms with "Sync servers from Laravel Forge" / submit
"Sync now" and toasts "{count} servers were saved."; gate it on `Create:ForgeServer`
(it has no authorization check today), run it on the queue (it makes one HTTP call per
server synchronously), and flag vanished servers rather than deleting them.
`forge_server_id` needs a unique constraint — the sync matches on it.

*Admin users* — grid `minmax(120px,1fr) minmax(0,1.3fr) minmax(0,1fr) 104px 26px`: name,
mono email, roles as mono chips on `--panel2`, mono verified date (`--warn` when `—`),
`⋯`. Footer note: password optional on edit and ignored when blank; email unique, checked
before submit. Both are quirks worth fixing — the shared schema demands a new password on
every edit, and duplicate emails surface as a database error rather than a field error.
The roles select needs a `GET /roles` endpoint that does not exist yet (spec gap 3).

---

## Interactions & behaviour

**Navigation.** Sidebar item → route. A nav item stays active for its child routes
(Customers is active on the customer detail; Subscriptions on the cockpit and the create
flow). Row clicks navigate where a detail page exists; otherwise the `⋯` menu carries the
actions. Use breadcrumbs rather than "Back to X" buttons.

**Theme.** Toggle in the sidebar footer swaps the token set on the host element and
`color-scheme` with it. Persist the choice.

**Secrets.** Every masked value has an explicit Reveal that flips to Hide. Reveal is
per-row and does not persist across navigation. Log reveals server-side.

**Polling.** Deployment jobs and pipeline status poll every 5s while any job is `pending`
or `running`, and stop otherwise. Show the polling state in the card header so the
operator knows the screen is live.

**Loading.** Synchronous Forge actions (Pull env, Generate logos, Sync from Forge) show
the button spinner and disable the cluster until they return.

**Animation.** Only two: `spin` 0.9s linear infinite on busy spinners, and
`breathe 1.6s ease-in-out infinite` (opacity 1 → 0.35 → 1) on anything actively running.
No entrance animations, no layout transitions.

**Empty states.** Heading "No {plural label}", body "Create a {label} to get started."

**Toasts.** Created / Saved / Deleted / Restored. Partial bulk failures use
"Deleted :count of :total" with ":count could not be deleted." Add the four missing
success toasts (Deploy Site, Generate Logos, Edit Server Details, Send Welcome Email).

**Confirmations.** Cancel is always labelled `Cancel`. Standard body "Are you sure you
would like to do this?" Destructive submit "Delete", restore "Restore". Custom copy per
action is in the spec. `{record}` interpolates the record title — for customers that is
`company_name`, not `name`; the Filament resource pointed at a column that does not exist,
so those headings render blank today.

**Empty value glyph.** Use `—` everywhere. Filament mixed `-` and `—`.

**Responsive.** Not a mobile product, but the panel must survive a ~900px viewport: every
multi-panel row is `repeat(auto-fit, minmax(<floor>, 1fr))` so it stacks, and every table
cell clips rather than wraps. Fixed-height rows plus wrapping text is the failure mode to
watch — it silently overflows into neighbouring rows.

**Permission gating.** `Ability:Model` PascalCase with a colon (`ViewAny:Customer`).
Sidebar item and list route need `ViewAny`, detail `View`, New `Create`, Edit `Update`,
Delete `Delete`, Restore `Restore`, Force delete `ForceDelete`. `super_admin` bypasses
everything. Gate on the client for UX only — the API enforces the same policies.

---

## State

Pinia stores:

- **auth** — token, user (id, name, email, roles, permissions), a `can(ability, model)`
  helper. Token is a Sanctum token with the `backend` ability, sent as
  `Authorization: Bearer`. It never expires, so the app owns its own session lifetime.
  Treat any `401` as expiry and route to `/login`; `403` is a permission failure.
- **ui** — theme, table density, sidebar state, per-list page size.
- Per-resource list state — filters, page, and for the cockpit the poll timer and the
  set of revealed secret ids.

Local component state: the guided flow's `urlConfirmed`, `postfix`, type slug and
vertical slug; active tab per tabbed screen.

---

## Assets

None. Every icon in the prototype is an inline 24×24 stroke path (`stroke-width` 1.6–2.4,
round caps and joins) at 11–15px — substitute the codebase's icon set, keeping one
distinct icon per sidebar item. Fonts are Geist and Geist Mono from Google Fonts. Logo
drop zones are placeholders for client-supplied artwork.

---

## Files

- `Reseller Admin.dc.html` — the full design reference. Open in a browser; nav, tabs,
  theme switch, and secret reveal are interactive. Mock data and all token values live in
  the `<script>` logic class at the bottom.

Read alongside the **Frontend Rebuild Spec** (structure, validation, copy, permissions,
the 30-item quirk register, and the 11 backend gaps) and `BACKEND_API.md`.

## Known blockers

Four gaps block screens as designed. Worth agreeing on before starting:

1. **No `search` parameter** on any list endpoint — the topbar search and every table search box.
2. **No sort parameters** — sortable column headers everywhere.
3. **Customer secrets stripped on every read** — blocks the S3/MinIO section of the customer form.
4. **No DNS verification endpoint** — blocks the guided flow's URL check and its pre-save validation.

Also missing: `GET /roles` (user form), password reset endpoints (or drop the link), a
global search endpoint, a URL uniqueness check, and any dashboard endpoint.
