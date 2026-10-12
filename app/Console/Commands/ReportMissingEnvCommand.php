<?php

namespace App\Console\Commands;

use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Models\TemplateEnvVariables;
use App\Services\EnvComparisonService;
use Illuminate\Console\Command;

/**
 * Read-only report of subscriptions whose environment lacks a key their
 * subscription type's template requires (for example COOKIE_DOMAIN, without
 * which the shared SSO cookie never leaves the tenant's own host).
 *
 * Database only, no Forge calls. Each key is checked in two places:
 * - `no_row`: the subscription has no EnvVariables row for it
 * - `empty`: the row is empty although the template has a value or needs a manual one
 * - `not_on_site`: it is absent from the env last pulled from Forge
 *
 * A key given by name that no template holds cannot be checked this way and is
 * reported as unknown; `--ignore-template` checks it on every subscription.
 */
class ReportMissingEnvCommand extends Command
{
    protected $signature = 'app:report-missing-env
        {keys?* : Keys to check (default: every template key of the subscription type)}
        {--type=* : Only these subscription type ids}
        {--ignore-template : Check the given keys on every subscription, even where the type template lacks them}
        {--json : Output JSON instead of a table}';

    protected $description = 'List subscriptions missing required env keys (read-only)';

    public function handle(EnvComparisonService $comparison): int
    {
        $keys = array_values(array_filter((array) $this->argument('keys')));
        $typeIds = array_map('intval', (array) $this->option('type'));

        $ignoreTemplate = (bool) $this->option('ignore-template');

        if ($ignoreTemplate && $keys === []) {
            $this->error('--ignore-template needs at least one key.');

            return self::INVALID;
        }

        $templates = TemplateEnvVariables::query()
            ->when($keys !== [], fn ($query) => $query->whereIn('key', $keys))
            ->when($typeIds !== [], fn ($query) => $query->whereIn('subscription_type_id', $typeIds))
            ->orderBy('key')
            ->get()
            ->groupBy('subscription_type_id');

        $unknownKeys = $ignoreTemplate
            ? []
            : array_values(array_diff($keys, $templates->flatten()->pluck('key')->unique()->all()));

        $issues = [];
        $checked = 0;

        $subscriptions = CustomerSubscription::query()
            ->when(! $ignoreTemplate, fn ($query) => $query->whereIn('subscription_type_id', $templates->keys()))
            ->when($ignoreTemplate && $typeIds !== [], fn ($query) => $query->whereIn('subscription_type_id', $typeIds))
            ->orderBy('subscription_type_id')
            ->orderBy('url')
            ->cursor();

        foreach ($subscriptions as $subscription) {
            $checked++;

            $configured = EnvVariables::query()
                ->where('customer_subscription_id', $subscription->id)
                ->pluck('value', 'key')
                ->all();

            $onSite = filled($subscription->env) ? $comparison->parseEnvContent((string) $subscription->env) : [];

            $typeTemplates = ($templates[$subscription->subscription_type_id] ?? collect())->keyBy('key');

            foreach ($ignoreTemplate ? $keys : $typeTemplates->keys() as $key) {
                $template = $typeTemplates->get($key);
                $problems = [];

                // A key outside the template was asked for by name, so an empty value counts as missing.
                if (! array_key_exists($key, $configured)) {
                    $problems[] = 'no_row';
                } elseif (blank($configured[$key]) && ($template === null || $template->requires_manual_fill || filled($template->value))) {
                    $problems[] = 'empty';
                }

                // An env that was never pulled (or is not a key=value file) says nothing about the site.
                if ($onSite !== [] && ! array_key_exists($key, $onSite)) {
                    $problems[] = 'not_on_site';
                }

                if ($problems !== []) {
                    $issues[] = [
                        'subscription_id' => $subscription->id,
                        'url' => (string) $subscription->url,
                        'subscription_type_id' => (int) $subscription->subscription_type_id,
                        'key' => $key,
                        'problems' => $problems,
                    ];
                }
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(
                ['checked' => $checked, 'unknown_keys' => $unknownKeys, 'issues' => $issues],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return $issues === [] && $unknownKeys === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($unknownKeys !== []) {
            $this->warn('Not in any required-env template'.($typeIds === [] ? '' : ' of the given types').', so not checked: '.implode(', ', $unknownKeys).'.');
            $this->line('Add the key to the template, or rerun with --ignore-template to check it on every subscription.');
        }

        if ($issues === [] && $unknownKeys !== []) {
            return self::FAILURE;
        }

        if ($issues === []) {
            $this->info("Checked {$checked} subscription(s): no required env keys are missing.");

            return self::SUCCESS;
        }

        $this->table(
            ['Subscription', 'URL', 'Type', 'Key', 'Problem'],
            array_map(fn (array $issue): array => [
                $issue['subscription_id'],
                $issue['url'],
                $issue['subscription_type_id'],
                $issue['key'],
                implode(', ', $issue['problems']),
            ], $issues),
        );

        $affected = count(array_unique(array_column($issues, 'subscription_id')));
        $this->warn("Checked {$checked} subscription(s): {$affected} have missing required env keys.");
        $this->line('no_row/empty: fix in Super Admin (app:sync-all-required-env-variables adds template rows). not_on_site: push the env to Forge and deploy.');

        return self::FAILURE;
    }
}
