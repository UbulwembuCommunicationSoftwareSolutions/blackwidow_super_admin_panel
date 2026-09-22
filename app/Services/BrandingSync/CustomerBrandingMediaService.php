<?php

namespace App\Services\BrandingSync;

use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use App\Models\CustomerBrandSlot;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionBrandSlot;
use App\Services\LogoSyncService;
use App\Support\BrandingSync\BrandingSyncPayload;
use App\Support\BrandingSync\CustomerBrandSlots;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CustomerBrandingMediaService
{
    /**
     * @return array<string, mixed>
     */
    public function presentMedia(CustomerBrandingMedia $media, Customer $customer): array
    {
        $assignedSlots = $customer->brandSlots()
            ->where('customer_branding_media_id', $media->id)
            ->pluck('slot')
            ->values()
            ->all();

        return [
            'id' => $media->id,
            'name' => $media->name,
            'url' => $media->url(),
            'checksum' => $media->checksum,
            'slot_assignments' => $assignedSlots,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentMediaLibrary(Customer $customer): array
    {
        $customer->loadMissing('brandSlots');

        return $customer->brandingMedia()
            ->orderByDesc('id')
            ->get()
            ->map(fn (CustomerBrandingMedia $media) => $this->presentMedia($media, $customer))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentCustomerBranding(Customer $customer): array
    {
        CustomerBrandSlots::ensureDefaults($customer);
        $customer->load(['brandSlots.media']);

        $slots = [];
        foreach (BrandingSyncPayload::SLOTS as $cmsSlot) {
            $row = $customer->brandSlots->firstWhere('slot', $cmsSlot);
            $media = $row?->media;
            $slots[$cmsSlot] = [
                'customer_branding_media_id' => $media?->id,
                'url' => $media?->url(),
                'checksum' => $media?->checksum,
            ];
        }

        return [
            'slots' => $slots,
            'media_library' => $this->presentMediaLibrary($customer),
        ];
    }

    public function storeUploadedMedia(Customer $customer, UploadedFile $file, ?string $name = null): CustomerBrandingMedia
    {
        $media = CustomerBrandingMedia::query()->create([
            'customer_id' => $customer->id,
            'name' => $name ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        ]);

        $media->addMedia($file)
            ->usingFileName(Str::uuid()->toString().'.'.($file->guessExtension() ?: 'bin'))
            ->toMediaCollection('file');

        $media->refreshChecksumFromFile();

        return $media->fresh();
    }

    public function assignCustomerDefault(Customer $customer, string $cmsSlot, ?int $mediaId): CustomerBrandSlot
    {
        CustomerBrandSlots::ensureDefaults($customer);

        if ($mediaId !== null) {
            CustomerBrandingMedia::query()
                ->where('customer_id', $customer->id)
                ->whereKey($mediaId)
                ->firstOrFail();
        }

        /** @var CustomerBrandSlot $slot */
        $slot = CustomerBrandSlot::query()->updateOrCreate(
            ['customer_id' => $customer->id, 'slot' => $cmsSlot],
            ['customer_branding_media_id' => $mediaId],
        );

        $this->propagateCustomerDefault($customer, $cmsSlot);

        return $slot->fresh(['media']);
    }

    public function propagateCustomerDefault(Customer $customer, string $cmsSlot): void
    {
        CustomerSubscription::query()
            ->where('customer_id', $customer->id)
            ->where('subscription_type_id', 1)
            ->each(function (CustomerSubscription $subscription) use ($cmsSlot): void {
                if ($this->subscriptionInheritsSlot($subscription, $cmsSlot)) {
                    $this->applyEffectiveBrandingToSubscription($subscription, $cmsSlot);
                }
            });
    }

    public function subscriptionInheritsSlot(CustomerSubscription $subscription, string $cmsSlot): bool
    {
        $row = $subscription->brandSlots()->where('slot', $cmsSlot)->first();

        return $row === null || ! $row->is_override;
    }

    public function applyEffectiveBrandingToSubscription(CustomerSubscription $subscription, string $cmsSlot): void
    {
        $media = $subscription->fresh()->effectiveBrandingMedia($cmsSlot);

        if ($media === null) {
            $this->clearSubscriptionLogo($subscription, $cmsSlot);

            return;
        }

        $this->copyMediaToSubscriptionLogo($subscription, $cmsSlot, $media);
    }

    /**
     * @param  array{action: string, customer_branding_media_id?: int|null, file?: UploadedFile}  $payload
     */
    public function updateSubscriptionBrandSlot(
        CustomerSubscription $subscription,
        string $cmsSlot,
        array $payload,
    ): CustomerSubscriptionBrandSlot {
        $subscription->loadMissing('customer');
        $customer = $subscription->customer;
        if ($customer === null) {
            throw new \RuntimeException('Subscription has no customer.');
        }

        $action = $payload['action'];

        if ($action === 'inherit') {
            CustomerSubscriptionBrandSlot::query()
                ->where('customer_subscription_id', $subscription->id)
                ->where('slot', $cmsSlot)
                ->delete();

            $this->applyEffectiveBrandingToSubscription($subscription->fresh(), $cmsSlot);

            return new CustomerSubscriptionBrandSlot([
                'customer_subscription_id' => $subscription->id,
                'slot' => $cmsSlot,
                'is_override' => false,
                'cleared' => false,
            ]);
        }

        if ($action === 'clear') {
            $row = CustomerSubscriptionBrandSlot::query()->updateOrCreate(
                [
                    'customer_subscription_id' => $subscription->id,
                    'slot' => $cmsSlot,
                ],
                [
                    'customer_branding_media_id' => null,
                    'is_override' => true,
                    'cleared' => true,
                ],
            );

            $this->clearSubscriptionLogo($subscription, $cmsSlot);

            return $row->fresh();
        }

        if ($action === 'assign') {
            $mediaId = (int) ($payload['customer_branding_media_id'] ?? 0);
            CustomerBrandingMedia::query()
                ->where('customer_id', $customer->id)
                ->whereKey($mediaId)
                ->firstOrFail();

            $row = CustomerSubscriptionBrandSlot::query()->updateOrCreate(
                [
                    'customer_subscription_id' => $subscription->id,
                    'slot' => $cmsSlot,
                ],
                [
                    'customer_branding_media_id' => $mediaId,
                    'is_override' => true,
                    'cleared' => false,
                ],
            );

            $this->applyEffectiveBrandingToSubscription($subscription->fresh(), $cmsSlot);

            return $row->fresh(['media']);
        }

        if ($action === 'upload') {
            /** @var UploadedFile $file */
            $file = $payload['file'];
            $media = $this->storeUploadedMedia($customer, $file);

            return $this->updateSubscriptionBrandSlot($subscription, $cmsSlot, [
                'action' => 'assign',
                'customer_branding_media_id' => $media->id,
            ]);
        }

        throw new \InvalidArgumentException('Unknown branding slot action: '.$action);
    }

    public function detachAndDeleteMedia(Customer $customer, CustomerBrandingMedia $media): void
    {
        if ((int) $media->customer_id !== (int) $customer->id) {
            abort(404);
        }

        $affectedCustomerSlots = CustomerBrandSlot::query()
            ->where('customer_id', $customer->id)
            ->where('customer_branding_media_id', $media->id)
            ->pluck('slot')
            ->all();

        CustomerBrandSlot::query()
            ->where('customer_id', $customer->id)
            ->where('customer_branding_media_id', $media->id)
            ->update(['customer_branding_media_id' => null]);

        CustomerSubscriptionBrandSlot::query()
            ->where('customer_branding_media_id', $media->id)
            ->update([
                'customer_branding_media_id' => null,
                'cleared' => true,
            ]);

        $media->delete();

        foreach ($affectedCustomerSlots as $cmsSlot) {
            $this->propagateCustomerDefault($customer, (string) $cmsSlot);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function presentSubscriptionBranding(CustomerSubscription $subscription): array
    {
        $subscription->loadMissing(['customer.brandSlots.media', 'customer.brandingMedia', 'brandSlots.media']);

        $customer = $subscription->customer;
        $mediaLibrary = $customer ? $this->presentMediaLibrary($customer) : [];

        $slots = [];
        foreach (BrandingSyncPayload::SLOTS as $cmsSlot) {
            $override = $subscription->brandSlots->firstWhere('slot', $cmsSlot);
            $customerSlot = $customer?->brandSlots?->firstWhere('slot', $cmsSlot);
            $effective = $subscription->effectiveBrandingMedia($cmsSlot);

            $source = 'none';
            if ($override !== null && $override->is_override) {
                $source = $override->cleared ? 'cleared' : 'override';
            } elseif ($customerSlot?->customer_branding_media_id) {
                $source = 'inherited';
            } elseif ($effective !== null) {
                $source = 'inherited';
            }

            $saSlot = BrandingSyncPayload::CMS_TO_SA_SLOT[$cmsSlot];
            $path = $subscription->getAttribute($saSlot);

            $slots[$cmsSlot] = [
                'sa_slot' => $saSlot,
                'url' => filled($path) ? LogoSyncService::absolutePublicUrl((string) $path) : null,
                'effective_url' => $effective?->url(),
                'source' => $source,
                'is_override' => (bool) ($override?->is_override),
                'cleared' => (bool) ($override?->cleared),
                'customer_branding_media_id' => $override?->customer_branding_media_id,
                'customer_default_media_id' => $customerSlot?->customer_branding_media_id,
                'customer_default_url' => $customerSlot?->media?->url(),
                'effective_media_id' => $effective?->id,
                'checksum' => $subscription->getAttribute(BrandingSyncPayload::checksumColumn($saSlot)),
                'updated_at' => optional($subscription->getAttribute(LogoSyncService::timestampColumn($saSlot)))?->toIso8601String(),
            ];
        }

        return [
            'slots' => $slots,
            'media_library' => $mediaLibrary,
            'logo_urls' => $subscription->logo_urls,
        ];
    }

    private function copyMediaToSubscriptionLogo(
        CustomerSubscription $subscription,
        string $cmsSlot,
        CustomerBrandingMedia $media,
    ): void {
        $saSlot = BrandingSyncPayload::CMS_TO_SA_SLOT[$cmsSlot] ?? null;
        if ($saSlot === null) {
            throw new \InvalidArgumentException("Unknown branding slot: {$cmsSlot}");
        }

        $spatieMedia = $media->getFirstMedia('file');
        if ($spatieMedia === null) {
            throw new \RuntimeException('Branding media has no file attached.');
        }

        $path = $spatieMedia->getPath();
        if (! is_readable($path)) {
            throw new \RuntimeException('Branding media file is not readable.');
        }

        $contents = file_get_contents($path);
        if ($contents === false || $contents === '') {
            throw new \RuntimeException('Branding media file is empty.');
        }

        $disk = Storage::disk('public');
        $oldPath = $subscription->getAttribute($saSlot);
        $extension = pathinfo($spatieMedia->file_name, PATHINFO_EXTENSION) ?: 'bin';
        $newPath = Str::uuid()->toString().'.'.$extension;
        $disk->put($newPath, $contents);

        $checksum = BrandingSyncPayload::computeChecksum($contents);
        $now = now();

        $subscription->fill([
            $saSlot => $newPath,
            LogoSyncService::timestampColumn($saSlot) => $now,
            BrandingSyncPayload::checksumColumn($saSlot) => $checksum,
        ]);
        $subscription->save();

        if (filled($oldPath) && $oldPath !== $newPath) {
            $disk->delete($oldPath);
        }

        $subscription->queueBrandingPush([$cmsSlot]);
    }

    private function clearSubscriptionLogo(CustomerSubscription $subscription, string $cmsSlot): void
    {
        $saSlot = BrandingSyncPayload::CMS_TO_SA_SLOT[$cmsSlot] ?? null;
        if ($saSlot === null) {
            throw new \InvalidArgumentException("Unknown branding slot: {$cmsSlot}");
        }

        $disk = Storage::disk('public');
        $oldPath = $subscription->getAttribute($saSlot);

        $subscription->fill([
            $saSlot => null,
            LogoSyncService::timestampColumn($saSlot) => now(),
            BrandingSyncPayload::checksumColumn($saSlot) => null,
        ]);
        $subscription->save();

        if (filled($oldPath)) {
            $disk->delete($oldPath);
        }

        $subscription->queueBrandingPush([$cmsSlot]);
    }
}
