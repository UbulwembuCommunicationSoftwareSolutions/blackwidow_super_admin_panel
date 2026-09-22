# Release Versioning Plan — GitHub Tags → Subscription Types → Deployed Sites

**Project:** blackwidow_super_admin_api
**Status:** Proposal
**Date:** 2026-09-22

## 1. Goal

Replace the free-text `master_version` / `deployed_version` pair with real, verifiable releases sourced from GitHub tags, so that:

- every subscription type points at a concrete GitHub release (tag + commit SHA) that new sites are provisioned with and existing sites are upgraded to;
- every customer subscription records the release that is *actually* running, confirmed after deploy rather than assumed before it;
- "outdated" sites, bulk upgrades, canary/pinned customers and rollbacks all become simple data operations on top of the existing deployment pipeline.

## 2. Current state (what we have today)

| Concern | Where it lives | Problem |
|---|---|---|
| Repo + branch per type | `subscription_types.github_repo`, `.branch` | Fine — Forge fixes the branch at site creation (`ForgeApi::createSite`), cannot be changed afterwards. |
| Target version per type | `subscription_types.master_version` (`string(8)`, nullable) | Free text. Not linked to any tag or commit. |
| Deployed version per site | `customer_subscriptions.deployed_version` (`string(8)`) | Written in `Jobs\SiteDeployment\DeploySite` **before** `deploySite()` is called: `deployed_version = subscriptionType->master_version`. A failed deploy leaves a wrong value. |
| Deploy script | `deployment_templates` (per type, `#WEBSITE_URL#` placeholder) → rendered into `deployment_scripts` (per subscription) by `AddDeploymentScriptOnForgeJob`, pushed via `ForgeApi::sendDeploymentScript` | Rendered once; presumably does a branch pull. No notion of a tag. |
| Tenant reporting | `Api\McpSiteController` and `Api\CrmController` accept `deployed_version` (`max:100`) from tenant apps | Exists but is an unstructured string. |
| Pipeline | `CustomerSubscriptionDeploymentJob` (batch_id, position, job_name, status, forge_status, forge_log) driven by `SiteDeploymentScheduler` / `DeploymentStepDispatcher`; step names in `SiteDeploymentJobName` | Solid. We reuse this as-is. |
| Backend API validation | `Backend\SubscriptionTypeController` (`master_version max:8`), `Backend\CustomerSubscriptionController` (`deployed_version max:8`) | Too short for `vX.Y.Z-rc.1`. |

Scope note: this plan covers the Laravel tenant apps deployed via Forge (CMS, firearm, etc.). The super-admin Vue SPA (`blackwidow_super_admin_frontend`, static `dist`) and the Capacitor responder app (app-store builds) have their own release cadences and are explicitly **out of scope**.

## 3. Release conventions on GitHub

1. One repo per tenant app (already the case via `subscription_types.github_repo`).
2. Releases are created as **GitHub Releases** on annotated tags named `vMAJOR.MINOR.PATCH`, optionally `vMAJOR.MINOR.PATCH-rc.N` for prereleases (mark them "pre-release" in GitHub).
3. Tags are cut from the type's `branch` (usually `main`/`master`). Hotfixes are tagged from a `release/vX.Y` branch if needed — the tag is what matters, not the branch.
4. The release body is the changelog; it is mirrored into the console so operators can read it before rolling out.
5. Tags are immutable. Never move or delete a released tag; publish a new patch instead.

## 4. Data model changes

### 4.1 New table `subscription_type_releases`

```php
Schema::create('subscription_type_releases', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_type_id')->constrained()->cascadeOnDelete();
    $table->string('tag', 64);                 // v2.4.0
    $table->string('commit_sha', 40);
    $table->string('name')->nullable();        // GitHub release title
    $table->longText('body')->nullable();      // release notes (markdown)
    $table->boolean('is_prerelease')->default(false);
    $table->boolean('is_draft')->default(false);
    $table->unsignedBigInteger('github_release_id')->nullable()->index();
    $table->timestamp('published_at')->nullable();
    $table->timestamp('synced_at')->nullable();
    $table->timestamps();

    $table->unique(['subscription_type_id', 'tag']);
});
```

Model `App\Models\SubscriptionTypeRelease` with `subscriptionType()` and scopes `stable()` / `published()`, plus a `semverSortKey()` helper (or sort by `published_at`).

### 4.2 `subscription_types`

```php
$table->foreignId('current_release_id')
      ->nullable()
      ->constrained('subscription_type_releases')
      ->nullOnDelete();
```

Semantics: **the release new sites get and the release all non-pinned sites should be on.** `master_version` is kept temporarily as a read-only mirror of `currentRelease->tag` (see §9), then dropped.

### 4.3 `customer_subscriptions`

```php
$table->foreignId('pinned_release_id')->nullable()
      ->constrained('subscription_type_releases')->nullOnDelete();   // operator override
$table->foreignId('deployed_release_id')->nullable()
      ->constrained('subscription_type_releases')->nullOnDelete();   // confirmed after deploy
$table->string('deployed_commit_sha', 40)->nullable();               // what's really on disk
$table->string('deployed_tag_raw', 64)->nullable();                  // as reported, even if unknown
$table->timestamp('deployed_confirmed_at')->nullable();
```

Effective target:

```php
public function targetRelease(): ?SubscriptionTypeRelease
{
    return $this->pinnedRelease ?? $this->subscriptionType?->currentRelease;
}

public function isOutdated(): bool
{
    $target = $this->targetRelease();
    return $target && $this->deployed_release_id !== $target->id;
}
```

`deployed_version` is kept temporarily, then dropped (§9).

### 4.4 `deployment_templates`

No schema change. Templates gain a `#RELEASE_TAG#` placeholder alongside the existing `#WEBSITE_URL#`.

## 5. Syncing releases from GitHub

### 5.1 `SyncGithubReleasesJob` (per subscription type)

- Calls `GET https://api.github.com/repos/{github_repo}/releases?per_page=100` (paginate) with a fine-grained PAT stored in `config('services.github.token')`.
- For each release: `updateOrCreate` by `(subscription_type_id, tag)` with `commit_sha` resolved via `GET /repos/{repo}/git/ref/tags/{tag}` (deref annotated tag objects to the commit).
- Skips drafts unless explicitly requested. Marks releases deleted on GitHub as `is_draft = true` rather than deleting rows (they may be referenced by deployed sites).
- Scheduled hourly in `routes/console.php`:
  `Schedule::job(new SyncAllGithubReleasesJob)->hourly();`

### 5.2 Webhook (fast path)

- Route: `POST /api/webhooks/github/releases` in `routes/api.php`, verified with `X-Hub-Signature-256` against `config('services.github.webhook_secret')`.
- On `release.published` / `release.edited` / `release.deleted` → dispatch `SyncGithubReleasesJob` for every type whose `github_repo` matches `repository.full_name`. One repo may back several subscription types (e.g. CMS variants); all get the row.
- Webhook is a convenience; the hourly sync is the source of truth.

### 5.3 Auto-promote (optional, off by default)

`subscription_types.auto_promote_stable` (boolean). When true, publishing a non-prerelease release sets `current_release_id` automatically. Recommend leaving off — promotion should be an explicit operator action in the console.

## 6. Deployment changes

### 6.1 Deployment template

Because Forge locks the branch at site creation, we keep `branch` as the clone source but deploy by tag:

```bash
cd $FORGE_SITE_PATH

git fetch --tags --force origin
git checkout --force #RELEASE_TAG#

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan optimize
    $FORGE_PHP artisan queue:restart
fi

echo "#RELEASE_TAG#" > VERSION
echo "$(git rev-parse HEAD)" > VERSION_SHA
```

The site sits on a detached HEAD at the tag, which is fine because nothing pulls on the tenant. Rollback is just deploying an older tag with the same script.

### 6.2 Rendering the script per deploy

`AddDeploymentScriptOnForgeJob` currently renders the template once and never again. Change it to **always re-render** from the template with the current target:

```php
$target = $customerSubscription->targetRelease();
if (! $target) {
    throw new \RuntimeException("No target release for subscription {$customerSubscription->id}.");
}

$rendered = str_replace(
    ['#WEBSITE_URL#', '#RELEASE_TAG#'],
    [$customerSubscription->domain, $target->tag],
    $deploymentTemplate->script
);

DeploymentScript::updateOrCreate(
    ['customer_subscription_id' => $customerSubscription->id],
    ['script' => $rendered, 'rendered_release_id' => $target->id]
);
$forgeApi->sendDeploymentScript($customerSubscription);
```

Add `deployment_scripts.rendered_release_id` (nullable FK) so we can tell which tag the script on Forge currently contains and skip the Forge call when unchanged.

If operators have hand-edited `deployment_scripts.script` for a site, add a boolean `deployment_scripts.is_custom`; custom scripts are still passed through the placeholder replacement but not overwritten from the template.

### 6.3 `DeploySite` step

- **Remove** `deployed_version = master_version` before the deploy.
- Record intent on the pipeline job instead: `CustomerSubscriptionDeploymentJob.parameters['release_id']` (already a JSON column).
- After `waitForDeploymentStatus` returns `finished`, confirm the deployed release (§7). Only then set `deployed_release_id`, `deployed_commit_sha`, `deployed_confirmed_at`, `deployed_at`.
- On failure, leave the deployed columns untouched — the previous release is still running.

### 6.4 Upgrade pipeline

Add a new batch shape in `SiteDeploymentScheduler`: `scheduleUpgrade(CustomerSubscription $sub)` queuing only `ADD_DEPLOYMENT_SCRIPT → DEPLOY_SITE` (existing step names, no new job classes). A `RedeployOutdatedSitesJob` fans this out for `CustomerSubscription::whereOutdated()`, throttled (e.g. 3 concurrent per server) using the existing `batch_id`/`position` mechanics.

## 7. Confirming what is actually deployed

Two complementary sources; use both, prefer (a).

**(a) Forge deployment record.** After `finished`, `GET /orgs/{org}/servers/{server}/sites/{site}/deployments/{id}` returns `commit_hash`. Match to `subscription_type_releases.commit_sha` → `deployed_release_id`. If no match, store the SHA and leave `deployed_release_id` null (shows as "unknown commit" in the console).

**(b) Tenant self-report.** Extend the payloads `McpSiteController` and `CrmController` already accept:

```php
'deployed_version'    => ['nullable', 'string', 'max:64'],   // tag from VERSION file
'deployed_commit_sha' => ['nullable', 'string', 'size:40'],  // from VERSION_SHA
```

Tenant apps read `VERSION` / `VERSION_SHA` at boot (cache them) and include them in their existing heartbeat / sync calls to the super-admin API. This catches drift from manual deploys done directly in Forge. Resolution rule: match on `commit_sha` first, then on `tag`; always store `deployed_tag_raw` verbatim.

Tenants should also expose `APP_VERSION` via a tiny `/api/version` endpoint for support use.

## 8. Console (Filament + backend API)

**Subscription type**
- Releases relation manager: tag, published date, prerelease badge, "current" marker, number of sites on it, release notes expandable.
- Actions: **Set as current** (confirm dialog showing how many sites become outdated), **Sync from GitHub now**.
- `SubscriptionTypeForm`: replace the `master_version` text input with a `current_release_id` select filtered to that type's published releases.

**Customer subscriptions**
- Columns: Target release, Deployed release, status badge (`up to date` / `outdated` / `unknown` / `pinned`), `deployed_confirmed_at`.
- Filters: outdated only, by type, by release.
- Row actions: **Pin to release…**, **Unpin**, **Upgrade now** (schedules §6.4), **Roll back to previous deployed release** (pin + upgrade).
- Bulk action: **Upgrade selected to target**.
- Type-level header action: **Upgrade all outdated sites of this type**.

**Backend API (`routes/backend-api.php`)**
- `GET/POST /subscription-types/{id}/releases`, `POST .../releases/{release}/promote`, `POST .../releases/sync`.
- `PATCH /customer-subscriptions/{id}` gains `pinned_release_id`; `POST /customer-subscriptions/{id}/upgrade`.
- Widen `deployed_version` / `master_version` validators to 64 during transition, then remove.

**Exports** — `CustomerSubscriptionExport`: swap `deployed_version` / `subscriptionType.master_version` for `deployedRelease.tag` / `targetRelease.tag`.

## 9. Migration and rollout

1. **Ship schema + models + sync** (no behaviour change). Run `SyncAllGithubReleasesJob` once. Tag current production commits on each repo (e.g. `v1.0.0`) if untagged.
2. **Backfill.** For each type: `current_release_id` = release whose tag matches `master_version`, else the latest stable tag. For each subscription: `deployed_release_id` = release matching `deployed_version` if any; otherwise leave null and let the next tenant heartbeat / deploy confirm it. Keep `master_version` / `deployed_version` populated via model observers as read-only mirrors so existing frontend code (`CustomerSubscriptionController` `masterVersion`/`deployedVersion`) keeps working.
3. **Update deployment templates** with the `#RELEASE_TAG#` block (§6.1) and deploy §6.2/§6.3 changes. Provision one new test site to validate end-to-end.
4. **Canary.** Pin one internal/test subscription to a prerelease, upgrade it, confirm §7 records the right SHA.
5. **Cut over the console** (§8) and the backend API.
6. **Cleanup** (after two clean release cycles): drop `master_version`, `deployed_version`, the observers, and the widened legacy validators.

## 10. Edge cases and decisions

- **Repo shared by several types.** Releases are stored per type (unique on `type_id + tag`), so one GitHub release produces one row per type. Simple and keeps `current_release_id` independent per type.
- **Deleted/moved tags.** Rows are soft-marked, never deleted; the console flags "release no longer on GitHub". Deploys to such a tag are blocked.
- **Manual Forge deploys.** Tenant self-report (§7b) detects them; the console shows `unknown commit` with the SHA so it can be investigated.
- **Downgrades with migrations.** Rollback is only safe when no irreversible migration ran. Add `requires_manual_rollback` (boolean, editable) on the release row so operators can flag releases with destructive migrations; the rollback action warns when crossing one.
- **Compatibility with the super-admin API.** If a tenant release needs a minimum super-admin API version, record it in the release notes front-matter (`min-superadmin: 3.2`) and parse it into `subscription_type_releases.min_superadmin_version` for a warning badge. Optional; skip in v1.
- **Rate limits.** Hourly sync across N types is well under GitHub's 5,000 req/h authenticated limit; use conditional requests (`ETag`) to be polite.

## 11. Work breakdown

| # | Item | Touches |
|---|---|---|
| 1 | Migrations + models (`SubscriptionTypeRelease`, new FKs, `deployment_scripts` columns) | `database/migrations`, `app/Models` |
| 2 | `GithubReleaseClient` service + `SyncGithubReleasesJob` / `SyncAllGithubReleasesJob` + schedule | `app/Services`, `app/Jobs`, `routes/console.php`, `config/services.php` |
| 3 | Webhook controller + signature middleware | `routes/api.php`, `app/Http/Controllers/Api` |
| 4 | Re-render deploy script with `#RELEASE_TAG#`; `is_custom` handling | `AddDeploymentScriptOnForgeJob`, `DeploymentTemplate` seed/update |
| 5 | `DeploySite`: remove pre-write, confirm via Forge deployment `commit_hash` | `Jobs/SiteDeployment/DeploySite.php`, `Helpers/ForgeApi.php` |
| 6 | Tenant self-report fields + resolver | `McpSiteController`, `CrmController`, tenant apps (`VERSION` read + heartbeat) |
| 7 | Upgrade batch (`scheduleUpgrade`, `RedeployOutdatedSitesJob`) | `SiteDeploymentScheduler`, `DeploymentStepDispatcher` |
| 8 | Filament resources, backend API endpoints, export | `app/Filament`, `Backend\*Controller`, `routes/backend-api.php` |
| 9 | Backfill command + observers for legacy columns | `app/Console/Commands`, `app/Observers` |
| 10 | Tests: sync (fixtures), placeholder rendering, target resolution, confirm-after-deploy, webhook signature | `tests/Feature` |
| 11 | Cleanup migration dropping legacy columns | `database/migrations` |
