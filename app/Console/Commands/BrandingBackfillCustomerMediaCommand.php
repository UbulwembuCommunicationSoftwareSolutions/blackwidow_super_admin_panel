<?php

namespace App\Console\Commands;

use App\Models\CustomerBrandingMedia;
use App\Models\CustomerBrandSlot;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionBrandSlot;
use App\Support\BrandingSync\BrandingSyncPayload;
use App\Support\BrandingSync\CustomerBrandSlots;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandingBackfillCustomerMediaCommand extends Command
{
    protected $signature = 'branding:backfill-customer-media {--dry-run : Report without saving}';

    protected $description = 'Backfill CustomerBrandingMedia and brand slots from legacy subscription logo_1/2/3 paths';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('public');
        $processed = 0;
        $skipped = 0;

        CustomerSubscription::query()
            ->where(function ($query): void {
                foreach (BrandingSyncPayload::CMS_TO_SA_SLOT as $saSlot) {
                    $query->orWhereNotNull($saSlot);
                }
            })
            ->orderBy('id')
            ->chunkById(50, function ($subscriptions) use ($disk, $dryRun, &$processed, &$skipped): void {
                foreach ($subscriptions as $subscription) {
                    foreach (BrandingSyncPayload::CMS_TO_SA_SLOT as $cmsSlot => $saSlot) {
                        $path = $subscription->getAttribute($saSlot);
                        if (blank($path) || ! $disk->exists((string) $path)) {
                            continue;
                        }

                        $contents = $disk->get((string) $path);
                        $checksum = BrandingSyncPayload::computeChecksum($contents);

                        $subscription->loadMissing('customer');
                        $customer = $subscription->customer;
                        if ($customer === null) {
                            $skipped++;

                            continue;
                        }

                        $existing = CustomerBrandingMedia::query()
                            ->where('customer_id', $customer->id)
                            ->where('checksum', $checksum)
                            ->first();

                        if ($existing !== null) {
                            $media = $existing;
                        } elseif ($dryRun) {
                            $this->line("Would create media for customer {$customer->id} slot {$cmsSlot} from subscription {$subscription->id}");
                        } else {
                            $extension = pathinfo((string) $path, PATHINFO_EXTENSION) ?: 'bin';
                            $media = CustomerBrandingMedia::query()->create([
                                'customer_id' => $customer->id,
                                'name' => $cmsSlot,
                                'checksum' => $checksum,
                            ]);
                            $media
                                ->addMediaFromString($contents)
                                ->usingFileName(Str::uuid()->toString().'.'.$extension)
                                ->toMediaCollection('file');
                        }

                        if ($dryRun) {
                            $processed++;

                            continue;
                        }

                        CustomerBrandSlots::ensureDefaults($customer);

                        $customerSlot = CustomerBrandSlot::query()
                            ->where('customer_id', $customer->id)
                            ->where('slot', $cmsSlot)
                            ->first();

                        if ($customerSlot !== null && $customerSlot->customer_branding_media_id === null) {
                            $customerSlot->skipSync = true;
                            $customerSlot->fill([
                                'customer_branding_media_id' => $media->id,
                            ]);
                            $customerSlot->save();
                        }

                        $updatedAt = $subscription->getAttribute($saSlot.'_updated_at') ?? now();
                        $override = CustomerSubscriptionBrandSlot::query()->firstOrNew([
                            'customer_subscription_id' => $subscription->id,
                            'slot' => $cmsSlot,
                        ]);
                        $override->skipSync = true;
                        $override->fill([
                            'is_override' => true,
                            'cleared' => false,
                            'customer_branding_media_id' => $media->id,
                            'updated_at' => $updatedAt,
                        ]);
                        $override->save();

                        $processed++;
                    }
                }
            });

        $this->info("Backfill complete. Slots processed: {$processed}, skipped: {$skipped}".($dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }
}
