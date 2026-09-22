<?php

namespace App\Support\BrandingSync;

use App\Models\Customer;
use App\Models\CustomerBrandingMedia;
use App\Models\CustomerBrandSlot;
use App\Models\CustomerSubscription;
use App\Models\CustomerSubscriptionBrandSlot;
use App\Services\LogoSyncService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Canonical wire shape for a single branding asset (logo slot).
 *
 * Both directions of Super Admin <-> tenant CMS sync serialise to and parse
 * from exactly these keys. Slots use CMS names on the wire; the panel maps
 * them to logo_1/2/3 internally.
 */
final class BrandingSyncPayload
{
    /** @var array<string, string> CMS slot => SuperAdmin column */
    public const CMS_TO_SA_SLOT = [
        'login_logo' => 'logo_1',
        'menu_logo' => 'logo_2',
        'login_background' => 'logo_3',
    ];

    /** @var list<string> */
    public const SLOTS = ['login_logo', 'menu_logo', 'login_background'];

    public function __construct(
        public readonly string $slot,
        public readonly ?string $url,
        public readonly ?string $checksum,
        public readonly bool $cleared,
        public readonly ?CarbonInterface $updatedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slot: (string) ($data['slot'] ?? ''),
            url: isset($data['url']) && filled($data['url']) ? (string) $data['url'] : null,
            checksum: isset($data['checksum']) && filled($data['checksum']) ? (string) $data['checksum'] : null,
            cleared: filter_var($data['cleared'] ?? false, FILTER_VALIDATE_BOOLEAN),
            updatedAt: isset($data['updated_at']) && filled($data['updated_at'])
                ? Carbon::parse($data['updated_at'])
                : null,
        );
    }

    public static function fromSubscription(CustomerSubscription $subscription, string $cmsSlot): self
    {
        if (! in_array($cmsSlot, self::SLOTS, true)) {
            throw new \InvalidArgumentException("Unknown branding slot: {$cmsSlot}");
        }

        $subscription->loadMissing('customer');

        $subscriptionSlot = CustomerSubscriptionBrandSlot::query()
            ->where('customer_subscription_id', $subscription->id)
            ->where('slot', $cmsSlot)
            ->first();

        if ($subscriptionSlot !== null && $subscriptionSlot->is_override && $subscriptionSlot->cleared) {
            return new self(
                slot: $cmsSlot,
                url: null,
                checksum: null,
                cleared: true,
                updatedAt: $subscriptionSlot->updated_at,
            );
        }

        $media = $subscription->effectiveBrandingMedia($cmsSlot);
        if ($media !== null) {
            return self::fromBrandingMedia($media, $cmsSlot, self::effectiveUpdatedAt($subscription, $cmsSlot, $media));
        }

        if (! self::subscriptionHasBrandSlotRows($subscription)) {
            return self::fromSubscriptionLegacyColumns($subscription, $cmsSlot);
        }

        return new self(
            slot: $cmsSlot,
            url: null,
            checksum: null,
            cleared: true,
            updatedAt: $subscriptionSlot?->updated_at,
        );
    }

    public static function fromCustomerDefault(Customer $customer, string $cmsSlot): self
    {
        if (! in_array($cmsSlot, self::SLOTS, true)) {
            throw new \InvalidArgumentException("Unknown branding slot: {$cmsSlot}");
        }

        $slot = CustomerBrandSlot::query()
            ->where('customer_id', $customer->id)
            ->where('slot', $cmsSlot)
            ->first();

        if ($slot === null || $slot->customer_branding_media_id === null) {
            return new self(
                slot: $cmsSlot,
                url: null,
                checksum: null,
                cleared: true,
                updatedAt: $slot?->updated_at,
            );
        }

        $slot->loadMissing('media');
        $media = $slot->media;
        if ($media === null) {
            return new self(
                slot: $cmsSlot,
                url: null,
                checksum: null,
                cleared: true,
                updatedAt: $slot->updated_at,
            );
        }

        return self::fromBrandingMedia($media, $cmsSlot, $slot->updated_at);
    }

    public static function fromBrandingMedia(
        CustomerBrandingMedia $media,
        string $cmsSlot,
        ?CarbonInterface $updatedAt = null,
    ): self {
        $media->loadMissing('media');

        return new self(
            slot: $cmsSlot,
            url: $media->url(),
            checksum: filled($media->checksum) ? (string) $media->checksum : null,
            cleared: false,
            updatedAt: $updatedAt ?? $media->updated_at,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slot' => $this->slot,
            'url' => $this->url,
            'checksum' => $this->checksum,
            'cleared' => $this->cleared,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toLmsArray(int $superAdminCustomerId): array
    {
        return array_merge($this->toArray(), [
            'super_admin_customer_id' => $superAdminCustomerId,
        ]);
    }

    public function saSlot(): ?string
    {
        return self::CMS_TO_SA_SLOT[$this->slot] ?? null;
    }

    public static function checksumColumn(string $saSlot): string
    {
        return $saSlot.'_checksum';
    }

    public static function computeChecksum(string $contents): string
    {
        return 'sha256:'.hash('sha256', $contents);
    }

    public static function checksumFromDiskPath(string $relativePath): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return null;
        }

        return self::computeChecksum($disk->get($relativePath));
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    public static function validationRules(string $prefix = 'branding'): array
    {
        $key = $prefix === '' ? '' : $prefix.'.';

        return [
            $key.'slot' => ['required', 'string', 'in:'.implode(',', self::SLOTS)],
            $key.'url' => ['nullable', 'string', 'max:2048'],
            $key.'checksum' => ['nullable', 'string', 'max:128'],
            $key.'cleared' => ['sometimes', 'boolean'],
            $key.'updated_at' => ['nullable', 'date'],
        ];
    }

    private static function fromSubscriptionLegacyColumns(CustomerSubscription $subscription, string $cmsSlot): self
    {
        $saSlot = self::CMS_TO_SA_SLOT[$cmsSlot];

        $path = $subscription->getAttribute($saSlot);
        $updatedAt = $subscription->getAttribute(LogoSyncService::timestampColumn($saSlot));
        $checksum = $subscription->getAttribute(self::checksumColumn($saSlot));

        $url = null;
        if (filled($path)) {
            $url = LogoSyncService::absolutePublicUrl((string) $path);
        }

        return new self(
            slot: $cmsSlot,
            url: $url,
            checksum: filled($checksum) ? (string) $checksum : null,
            cleared: blank($path),
            updatedAt: $updatedAt ? Carbon::parse($updatedAt) : null,
        );
    }

    private static function subscriptionHasBrandSlotRows(CustomerSubscription $subscription): bool
    {
        if (CustomerSubscriptionBrandSlot::query()
            ->where('customer_subscription_id', $subscription->id)
            ->exists()) {
            return true;
        }

        if ($subscription->customer_id === null) {
            return false;
        }

        return CustomerBrandSlot::query()
            ->where('customer_id', $subscription->customer_id)
            ->whereNotNull('customer_branding_media_id')
            ->exists();
    }

    private static function effectiveUpdatedAt(
        CustomerSubscription $subscription,
        string $cmsSlot,
        CustomerBrandingMedia $media,
    ): ?CarbonInterface {
        $subscriptionSlot = CustomerSubscriptionBrandSlot::query()
            ->where('customer_subscription_id', $subscription->id)
            ->where('slot', $cmsSlot)
            ->first();

        $customerSlot = null;
        if ($subscription->customer_id !== null) {
            $customerSlot = CustomerBrandSlot::query()
                ->where('customer_id', $subscription->customer_id)
                ->where('slot', $cmsSlot)
                ->first();
        }

        $candidates = array_filter([
            $subscriptionSlot?->updated_at,
            $customerSlot?->updated_at,
            $media->updated_at,
        ]);

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sortByDesc(fn ($dt) => $dt?->getTimestamp())->first();
    }
}
