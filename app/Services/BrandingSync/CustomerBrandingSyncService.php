<?php

namespace App\Services\BrandingSync;

use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use App\Models\CustomerBrandSlot;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionBrandSlot;
use App\Support\BrandingSync\BrandingSyncOutcome;
use App\Support\BrandingSync\BrandingSyncPayload;
use App\Support\BrandingSync\CustomerBrandSlots;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Applies an inbound branding payload from a tenant onto brand-slot media.
 */
class CustomerBrandingSyncService
{
    /**
     * @return array{outcome: BrandingSyncOutcome, payload: BrandingSyncPayload}
     */
    public function upsertFromTenant(CustomerSubscription $subscription, BrandingSyncPayload $incoming): array
    {
        if ($incoming->saSlot() === null) {
            throw new \InvalidArgumentException('Unknown branding slot: '.$incoming->slot);
        }

        $subscription->loadMissing('customer');
        $local = BrandingSyncPayload::fromSubscription($subscription, $incoming->slot);

        if (
            filled($incoming->checksum)
            && filled($local->checksum)
            && hash_equals((string) $local->checksum, (string) $incoming->checksum)
            && $incoming->cleared === $local->cleared
        ) {
            return ['outcome' => BrandingSyncOutcome::Unchanged, 'payload' => $local];
        }

        $remoteTs = $incoming->updatedAt ?? now();
        $localTs = $local->updatedAt;

        if ($localTs && $remoteTs->lt($localTs)) {
            return ['outcome' => BrandingSyncOutcome::Stale, 'payload' => $local];
        }

        $hadLocal = filled($local->url) && ! $local->cleared;
        $subscription->skipSync = true;

        if ($incoming->cleared) {
            $this->clearSubscriptionSlot($subscription, $incoming->slot, $remoteTs);

            return [
                'outcome' => BrandingSyncOutcome::Cleared,
                'payload' => BrandingSyncPayload::fromSubscription($subscription->fresh(), $incoming->slot),
            ];
        }

        if (! filled($incoming->url)) {
            throw new \InvalidArgumentException('Branding url is required when cleared is false.');
        }

        $contents = $this->download($incoming->url);
        $checksum = BrandingSyncPayload::computeChecksum($contents);

        if (filled($incoming->checksum) && ! hash_equals($checksum, (string) $incoming->checksum)) {
            throw new \RuntimeException('Branding checksum mismatch for slot '.$incoming->slot);
        }

        $media = $this->findOrCreateMedia($subscription, $contents, $checksum, $incoming->slot);
        $this->applyMediaToSubscriptionSlot($subscription, $incoming->slot, $media, $remoteTs);
        $this->applyMediaToCustomerDefaultIfEmpty($subscription, $incoming->slot, $media, $remoteTs);

        $fresh = BrandingSyncPayload::fromSubscription($subscription->fresh(), $incoming->slot);

        return [
            'outcome' => $hadLocal ? BrandingSyncOutcome::Updated : BrandingSyncOutcome::Created,
            'payload' => $fresh,
        ];
    }

    /**
     * Apply branding from the shared LMS hub onto a customer's default slots.
     *
     * @return array{outcome: BrandingSyncOutcome, payload: BrandingSyncPayload}
     */
    public function upsertFromLmsHub(Customer $customer, BrandingSyncPayload $incoming): array
    {
        if ($incoming->saSlot() === null) {
            throw new \InvalidArgumentException('Unknown branding slot: '.$incoming->slot);
        }

        CustomerBrandSlots::ensureDefaults($customer);

        $local = BrandingSyncPayload::fromCustomerDefault($customer, $incoming->slot);

        if (
            filled($incoming->checksum)
            && filled($local->checksum)
            && hash_equals((string) $local->checksum, (string) $incoming->checksum)
            && $incoming->cleared === $local->cleared
        ) {
            return ['outcome' => BrandingSyncOutcome::Unchanged, 'payload' => $local];
        }

        $remoteTs = $incoming->updatedAt ?? now();
        $localTs = $local->updatedAt;

        if ($localTs && $remoteTs->lt($localTs)) {
            return ['outcome' => BrandingSyncOutcome::Stale, 'payload' => $local];
        }

        $hadLocal = filled($local->url) && ! $local->cleared;

        if ($incoming->cleared) {
            $this->clearCustomerDefaultSlot($customer, $incoming->slot, $remoteTs);

            return [
                'outcome' => BrandingSyncOutcome::Cleared,
                'payload' => BrandingSyncPayload::fromCustomerDefault($customer->fresh(), $incoming->slot),
            ];
        }

        if (! filled($incoming->url)) {
            throw new \InvalidArgumentException('Branding url is required when cleared is false.');
        }

        $contents = $this->download($incoming->url);
        $checksum = BrandingSyncPayload::computeChecksum($contents);

        if (filled($incoming->checksum) && ! hash_equals($checksum, (string) $incoming->checksum)) {
            throw new \RuntimeException('Branding checksum mismatch for slot '.$incoming->slot);
        }

        $media = $this->findOrCreateMediaForCustomer($customer, $contents, $checksum, $incoming->slot);
        $this->applyMediaToCustomerDefault($customer, $incoming->slot, $media, $remoteTs);

        return [
            'outcome' => $hadLocal ? BrandingSyncOutcome::Updated : BrandingSyncOutcome::Created,
            'payload' => BrandingSyncPayload::fromCustomerDefault($customer->fresh(), $incoming->slot),
        ];
    }

    private function clearSubscriptionSlot(CustomerSubscription $subscription, string $cmsSlot, mixed $remoteTs): void
    {
        $row = CustomerSubscriptionBrandSlot::query()->firstOrNew([
            'customer_subscription_id' => $subscription->id,
            'slot' => $cmsSlot,
        ]);
        $row->skipSync = true;
        $row->fill([
            'is_override' => true,
            'cleared' => true,
            'customer_branding_media_id' => null,
            'updated_at' => $remoteTs,
        ]);
        $row->save();
    }

    private function findOrCreateMedia(
        CustomerSubscription $subscription,
        string $contents,
        string $checksum,
        string $cmsSlot,
    ): CustomerBrandingMedia {
        $customer = $subscription->customer;
        if ($customer === null) {
            throw new \RuntimeException('Subscription has no customer for branding media.');
        }

        return $this->findOrCreateMediaForCustomer($customer, $contents, $checksum, $cmsSlot);
    }

    private function findOrCreateMediaForCustomer(
        Customer $customer,
        string $contents,
        string $checksum,
        string $cmsSlot,
    ): CustomerBrandingMedia {
        $existing = CustomerBrandingMedia::query()
            ->where('customer_id', $customer->id)
            ->where('checksum', $checksum)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $extension = $this->guessExtension($contents) ?? 'bin';
        $mediaRecord = CustomerBrandingMedia::query()->create([
            'customer_id' => $customer->id,
            'name' => $cmsSlot,
            'checksum' => $checksum,
        ]);

        $mediaRecord
            ->addMediaFromString($contents)
            ->usingFileName(Str::uuid()->toString().'.'.$extension)
            ->toMediaCollection('file');

        return $mediaRecord->fresh();
    }

    private function applyMediaToSubscriptionSlot(
        CustomerSubscription $subscription,
        string $cmsSlot,
        CustomerBrandingMedia $media,
        mixed $remoteTs,
    ): void {
        $row = CustomerSubscriptionBrandSlot::query()->firstOrNew([
            'customer_subscription_id' => $subscription->id,
            'slot' => $cmsSlot,
        ]);
        $row->skipSync = true;
        $row->fill([
            'is_override' => true,
            'cleared' => false,
            'customer_branding_media_id' => $media->id,
            'updated_at' => $remoteTs,
        ]);
        $row->save();
    }

    private function applyMediaToCustomerDefault(
        Customer $customer,
        string $cmsSlot,
        CustomerBrandingMedia $media,
        mixed $remoteTs,
    ): void {
        CustomerBrandSlots::ensureDefaults($customer);

        $slot = CustomerBrandSlot::query()
            ->where('customer_id', $customer->id)
            ->where('slot', $cmsSlot)
            ->first();

        if ($slot === null) {
            return;
        }

        $slot->skipSync = true;
        $slot->forceFill([
            'customer_branding_media_id' => $media->id,
            'updated_at' => $remoteTs,
        ])->save();
    }

    private function clearCustomerDefaultSlot(Customer $customer, string $cmsSlot, mixed $remoteTs): void
    {
        CustomerBrandSlots::ensureDefaults($customer);

        $slot = CustomerBrandSlot::query()
            ->where('customer_id', $customer->id)
            ->where('slot', $cmsSlot)
            ->first();

        if ($slot === null) {
            return;
        }

        $slot->skipSync = true;
        $slot->forceFill([
            'customer_branding_media_id' => null,
            'updated_at' => $remoteTs,
        ])->save();
    }

    private function applyMediaToCustomerDefaultIfEmpty(
        CustomerSubscription $subscription,
        string $cmsSlot,
        CustomerBrandingMedia $media,
        mixed $remoteTs,
    ): void {
        $customer = $subscription->customer;
        if ($customer === null) {
            return;
        }

        CustomerBrandSlots::ensureDefaults($customer);

        $slot = CustomerBrandSlot::query()
            ->where('customer_id', $customer->id)
            ->where('slot', $cmsSlot)
            ->first();

        if ($slot === null || $slot->customer_branding_media_id !== null) {
            return;
        }

        $slot->skipSync = true;
        $slot->forceFill([
            'customer_branding_media_id' => $media->id,
            'updated_at' => $remoteTs,
        ])->save();
    }

    private function download(string $url): string
    {
        $response = Http::timeout((int) config('branding_sync.timeout', 30))
            ->connectTimeout(10)
            ->get($url);

        if (! $response->successful()) {
            Log::error('Failed to download branding asset', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException('Failed to download branding asset from '.$url);
        }

        $body = $response->body();
        if ($body === '') {
            throw new \RuntimeException('Downloaded branding asset was empty: '.$url);
        }

        return $body;
    }

    private function guessExtension(string $contents): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($contents) ?: '';

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => null,
        };
    }
}
