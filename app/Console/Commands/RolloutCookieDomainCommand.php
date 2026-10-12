<?php

namespace App\Console\Commands;

use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\TemplateEnvVariables;
use App\Support\Sso\CookieDomain;
use Illuminate\Console\Command;

/**
 * Gives tenant sites the COOKIE_DOMAIN the shared SSO cookie needs, worked out
 * per site from its own URL ({@see CookieDomain}).
 *
 * A dry run unless `--apply` is passed. Applying writes Super Admin's own
 * rows only (each subscription's env variable); pushing the env to Forge
 * stays a separate, per-site step.
 *
 * The type templates are only touched with `--templates`: a template holds
 * one value for every new subscription of the type, and the hub's domain is
 * wrong for a site on any other domain.
 */
class RolloutCookieDomainCommand extends Command
{
    private const KEY = 'COOKIE_DOMAIN';

    /** Console, Firearm and Pre Case: the apps that sign in from the shared cookie. */
    private const DEFAULT_TYPES = [1, 2, 13];

    protected $signature = 'app:rollout-cookie-domain
        {--type=* : Subscription type ids (default: Console, Firearm, Pre Case)}
        {--domain= : Only sites whose proposed value is this one, e.g. .blackwidow.org.za}
        {--templates : Also add the key to the type templates, with the hub domain as its value}
        {--apply : Save the rows. Without it nothing is written}
        {--json : Output JSON instead of a table}';

    protected $description = 'Plan or apply COOKIE_DOMAIN for tenant sites, per site domain (dry run by default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $typeIds = array_map('intval', (array) $this->option('type')) ?: self::DEFAULT_TYPES;
        $hubDomain = CookieDomain::forUrl((string) config('app.url'));
        $onlyDomain = trim((string) $this->option('domain'));

        $rows = [];

        $subscriptions = CustomerSubscription::query()
            ->whereIn('subscription_type_id', $typeIds)
            ->orderBy('subscription_type_id')
            ->orderBy('url')
            ->get();

        foreach ($subscriptions as $subscription) {
            $current = EnvVariables::query()
                ->where('customer_subscription_id', $subscription->id)
                ->where('key', self::KEY)
                ->first();

            $proposed = CookieDomain::forUrl($subscription->url);

            if ($onlyDomain !== '' && $proposed !== $onlyDomain) {
                continue;
            }

            $action = match (true) {
                $proposed === null => 'skip',
                $current === null => 'add',
                blank($current->value) => 'fill',
                $current->value === $proposed => 'ok',
                default => 'keep',
            };

            if ($apply && in_array($action, ['add', 'fill'], true)) {
                EnvVariables::query()->updateOrCreate(
                    ['customer_subscription_id' => $subscription->id, 'key' => self::KEY],
                    ['value' => $proposed],
                );
            }

            $rows[] = [
                'subscription_id' => $subscription->id,
                'subscription_type_id' => (int) $subscription->subscription_type_id,
                'url' => (string) $subscription->url,
                'current' => $current?->value,
                'proposed' => $proposed,
                'action' => $action,
                'hub_handoff' => $proposed !== null && $proposed === $hubDomain,
            ];
        }

        $templatesAdded = $this->option('templates') ? $this->ensureTemplates($typeIds, $hubDomain, $apply) : [];

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['applied' => $apply, 'hub_domain' => $hubDomain, 'templates_added' => $templatesAdded, 'sites' => $rows],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        $this->table(
            ['Subscription', 'Type', 'URL', 'Current', 'Proposed', 'Action', 'Super Admin / LMS handoff'],
            array_map(fn (array $row): array => [
                $row['subscription_id'],
                $row['subscription_type_id'],
                $row['url'],
                $row['current'] ?? '-',
                $row['proposed'] ?? '-',
                $row['action'],
                $row['hub_handoff'] ? 'yes' : 'no',
            ], $rows),
        );

        $counts = array_count_values(array_column($rows, 'action'));
        $summary = implode(', ', array_map(fn (string $action, int $count): string => "{$count} {$action}", array_keys($counts), $counts));

        $this->line('add/fill: set to the proposed value. ok: already right. keep: has a different value, left alone. skip: no shareable parent domain.');
        $this->line("Handoff 'no': the site is not under {$hubDomain}, so its cookie can only be shared with apps on its own domain.");
        $this->info(($apply ? 'Applied' : 'Dry run').': '.($summary !== '' ? $summary : 'no subscriptions').'; '
            .count($templatesAdded).' template(s) '.($apply ? 'added' : 'to add').'.');

        if ($apply) {
            $this->line('Nothing was pushed to Forge. Push each site with app:send-site-envs {id} (it also deploys the site).');
        }

        return self::SUCCESS;
    }

    /**
     * New subscriptions take their env from the type template. Its value is
     * the hub's own domain, which only suits sites under that domain.
     *
     * @param  list<int>  $typeIds
     * @return list<int> Type ids whose template lacked the key.
     */
    private function ensureTemplates(array $typeIds, ?string $hubDomain, bool $apply): array
    {
        $have = TemplateEnvVariables::query()
            ->whereIn('subscription_type_id', $typeIds)
            ->where('key', self::KEY)
            ->pluck('subscription_type_id')
            ->all();

        $missing = array_values(array_diff($typeIds, $have));

        if ($apply && $hubDomain !== null) {
            foreach ($missing as $typeId) {
                TemplateEnvVariables::query()->create([
                    'subscription_type_id' => $typeId,
                    'key' => self::KEY,
                    'value' => $hubDomain,
                ]);
            }
        }

        return $missing;
    }
}
