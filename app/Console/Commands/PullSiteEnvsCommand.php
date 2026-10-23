<?php

namespace App\Console\Commands;

use App\Models\CustomerSubscription;
use App\Support\SiteEnv\SiteEnvPuller;
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
 * - unpushed edit: with --keep-unpushed, a row changed in Super Admin since the last sync, left alone
 * - only here: rows the site does not have, left alone
 */
class PullSiteEnvsCommand extends Command
{
    protected $signature = 'app:pull-site-envs
        {ids?* : Only these subscription ids}
        {--type=* : Only these subscription type ids}
        {--apply : Save the rows. Without it nothing is written}
        {--keys : List the key names behind each count}
        {--keep-unpushed : Leave rows edited in Super Admin since the last sync alone (they are still waiting to be pushed)}
        {--pause=0 : Milliseconds to wait between sites, to stay under the Forge API rate limit}';

    protected $description = 'Refresh env variable rows from each site\'s real .env on Forge (dry run by default)';

    public function handle(SiteEnvReader $reader, SiteEnvPuller $puller): int
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
        $totals = ['add' => 0, 'change' => 0, 'kept' => 0, 'pending' => 0, 'failed' => 0, 'skipped' => 0];
        $keepUnpushed = (bool) $this->option('keep-unpushed');
        $pause = max(0, (int) $this->option('pause'));

        foreach ($subscriptions as $index => $subscription) {
            $label = [$subscription->id, (int) $subscription->subscription_type_id, (string) $subscription->url];

            if (blank($subscription->server_id) || blank($subscription->forge_site_id)) {
                $totals['skipped']++;
                $rows[] = [...$label, '-', '-', '-', '-', '-', 'no Forge site'];

                continue;
            }

            if ($pause > 0 && $index > 0) {
                usleep($pause * 1000);
            }

            try {
                $content = $reader->read($subscription);
            } catch (\Throwable $e) {
                $totals['failed']++;
                $rows[] = [...$label, '-', '-', '-', '-', '-', 'failed: '.mb_strimwidth($e->getMessage(), 0, 60, '…')];

                continue;
            }

            $pulled = $puller->pull($subscription, $content, $apply, $keepUnpushed);

            if ($pulled['skipped']) {
                $totals['skipped']++;
                $rows[] = [...$label, '-', '-', '-', '-', '-', 'site env is empty'];

                continue;
            }

            $totals['add'] += count($pulled['add']);
            $totals['change'] += count($pulled['change']);
            $totals['kept'] += count($pulled['kept']);
            $totals['pending'] += count($pulled['pending']);
            $rows[] = [...$label, count($pulled['add']), count($pulled['change']), count($pulled['kept']), count($pulled['pending']), count($pulled['only_here']), 'ok'];

            if ($this->option('keys')) {
                foreach (['add' => $pulled['add'], 'change' => $pulled['change'], 'kept' => $pulled['kept'], 'unpushed edit' => $pulled['pending'], 'only here' => $pulled['only_here']] as $name => $keys) {
                    if ($keys !== []) {
                        $rows[] = ['', '', "  {$name}: ".implode(', ', $keys), '', '', '', '', '', ''];
                    }
                }
            }
        }

        $this->table(['Subscription', 'Type', 'URL', 'Add', 'Change', 'Kept', 'Unpushed', 'Only here', 'Result'], $rows);

        $this->info(sprintf(
            '%s: %d site(s), %d key(s) added, %d changed, %d kept (site value empty), %d unpushed edit(s) left alone; %d failed, %d skipped.',
            $apply ? 'Applied' : 'Dry run',
            count($subscriptions) - $totals['failed'] - $totals['skipped'],
            $totals['add'],
            $totals['change'],
            $totals['kept'],
            $totals['pending'],
            $totals['failed'],
            $totals['skipped'],
        ));

        return $totals['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
