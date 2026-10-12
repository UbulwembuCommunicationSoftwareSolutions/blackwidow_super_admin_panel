<?php

namespace App\Console\Commands;

use App\Models\CustomerSubscription;
use App\Models\EnvVariables;
use App\Support\SiteEnv\SiteEnvReader;
use Illuminate\Console\Command;

/**
 * Brings Super Admin's env variable rows back in line with the .env each
 * site is really running, so a later push does not undo changes made on the
 * site by hand.
 *
 * A dry run unless `--apply` is passed, and it only ever prints key counts:
 * - add: on the site, no row here
 * - change: both have it, the values differ, and the site's is taken
 * - kept: the site's value is empty but the row has one, so the row is left
 * - only here: rows the site does not have, left alone
 */
class PullSiteEnvsCommand extends Command
{
    /** Never stored: it would hand every tenant row the Forge account. */
    private const IGNORED_KEYS = ['FORGE_API_KEY'];

    protected $signature = 'app:pull-site-envs
        {ids?* : Only these subscription ids}
        {--type=* : Only these subscription type ids}
        {--apply : Save the rows. Without it nothing is written}
        {--keys : List the key names behind each count}';

    protected $description = 'Refresh env variable rows from each site\'s real .env on Forge (dry run by default)';

    public function handle(SiteEnvReader $reader): int
    {
        $apply = (bool) $this->option('apply');
        $ids = array_map('intval', (array) $this->argument('ids'));
        $typeIds = array_map('intval', (array) $this->option('type'));

        $subscriptions = CustomerSubscription::query()
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($typeIds !== [], fn ($query) => $query->whereIn('subscription_type_id', $typeIds))
            ->orderBy('subscription_type_id')
            ->orderBy('url')
            ->get();

        $rows = [];
        $totals = ['add' => 0, 'change' => 0, 'kept' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($subscriptions as $subscription) {
            $label = [$subscription->id, (int) $subscription->subscription_type_id, (string) $subscription->url];

            if (blank($subscription->server_id) || blank($subscription->forge_site_id)) {
                $totals['skipped']++;
                $rows[] = [...$label, '-', '-', '-', '-', 'no Forge site'];

                continue;
            }

            try {
                $content = $reader->read($subscription);
            } catch (\Throwable $e) {
                $totals['failed']++;
                $rows[] = [...$label, '-', '-', '-', '-', 'failed: '.mb_strimwidth($e->getMessage(), 0, 60, '…')];

                continue;
            }

            $site = array_diff_key(SiteEnvReader::parse($content), array_flip(self::IGNORED_KEYS));

            if ($site === []) {
                $totals['skipped']++;
                $rows[] = [...$label, '-', '-', '-', '-', 'site env is empty'];

                continue;
            }

            $existing = EnvVariables::query()
                ->where('customer_subscription_id', $subscription->id)
                ->get()
                ->keyBy('key');

            $add = $change = $kept = [];

            foreach ($site as $key => $value) {
                $row = $existing->get($key);

                if ($row === null) {
                    $add[] = $key;
                } elseif ((string) $row->value === $value) {
                    continue;
                } elseif ($value === '') {
                    $kept[] = $key;

                    continue;
                } else {
                    $change[] = $key;
                }

                if ($apply) {
                    EnvVariables::query()->updateOrCreate(
                        ['customer_subscription_id' => $subscription->id, 'key' => $key],
                        ['value' => $value],
                    );
                }
            }

            if ($apply) {
                $subscription->forceFill(['env' => $content])->saveQuietly();
            }

            $onlyHere = $existing->keys()->diff(array_keys($site))->values()->all();

            $totals['add'] += count($add);
            $totals['change'] += count($change);
            $totals['kept'] += count($kept);
            $rows[] = [...$label, count($add), count($change), count($kept), count($onlyHere), 'ok'];

            if ($this->option('keys')) {
                foreach (['add' => $add, 'change' => $change, 'kept' => $kept, 'only here' => $onlyHere] as $name => $keys) {
                    if ($keys !== []) {
                        $rows[] = ['', '', "  {$name}: ".implode(', ', $keys), '', '', '', '', ''];
                    }
                }
            }
        }

        $this->table(['Subscription', 'Type', 'URL', 'Add', 'Change', 'Kept', 'Only here', 'Result'], $rows);

        $this->info(sprintf(
            '%s: %d site(s), %d key(s) added, %d changed, %d kept (site value empty); %d failed, %d skipped.',
            $apply ? 'Applied' : 'Dry run',
            count($subscriptions) - $totals['failed'] - $totals['skipped'],
            $totals['add'],
            $totals['change'],
            $totals['kept'],
            $totals['failed'],
            $totals['skipped'],
        ));

        return $totals['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
