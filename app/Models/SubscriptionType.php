<?php

namespace App\Models;

use App\Services\CustomerSubscriptionService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SubscriptionType extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Host label for each product id. Display names like "Firearm Module"
     * must not leak into tenant URLs (demo.firearm.blackwidow.org.za, not
     * demo.firearm-module.blackwidow.org.za).
     *
     * @var array<int, string>
     */
    public const URL_SLUGS = [
        1 => 'console',
        2 => 'firearm',
        3 => 'responder',
        4 => 'reporter',
        5 => 'security',
        6 => 'driver',
        7 => 'survey',
        8 => 'DONOTUSE',
        9 => 'time',
        10 => 'stock',
        11 => 'information',
    ];

    protected $fillable = [
        'name',
        'github_repo',
        'project_type',
        'public_dir',
        'branch',
        'master_version',
        'auto_promote_stable',
        'current_release_id',
    ];

    protected $appends = ['logo_descriptions', 'url_slug'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_promote_stable' => 'boolean',
        ];
    }

    public function releases(): HasMany
    {
        return $this->hasMany(SubscriptionTypeRelease::class);
    }

    public function currentRelease(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTypeRelease::class, 'current_release_id');
    }

    /**
     * What each of the five subscription logo slots means for this product, so
     * API clients can label the upload fields without duplicating the mapping.
     * Unused slots are returned as null rather than "Not Used", leaving the
     * caller to decide whether to render them.
     *
     * @return Attribute<list<string|null>, never>
     */
    protected function logoDescriptions(): Attribute
    {
        return Attribute::make(
            get: fn (): array => array_map(
                fn (string $label): ?string => $label === 'Not Used' ? null : $label,
                CustomerSubscriptionService::getLogoDescriptions($this->id),
            ),
        );
    }

    /**
     * Product segment used in `{customer}.{slug}.{vertical}` hostnames.
     *
     * @return Attribute<string, never>
     */
    protected function urlSlug(): Attribute
    {
        return Attribute::make(
            get: fn (): string => self::urlSlugFor($this->id, $this->name),
        );
    }

    public static function urlSlugFor(?int $id, ?string $name = null): string
    {
        if ($id !== null && array_key_exists($id, self::URL_SLUGS)) {
            return self::URL_SLUGS[$id];
        }

        $slug = Str::slug((string) $name);

        return match ($slug) {
            'firearm-module' => 'firearm',
            '' => 'unknown',
            default => $slug,
        };
    }

    /**
     * Rewrite a host or URL that still uses a display-name slug (e.g.
     * firearm-module) onto the canonical product label.
     */
    public static function canonicalizeHost(string $host, ?int $subscriptionTypeId, ?string $name = null): string
    {
        $slug = self::urlSlugFor($subscriptionTypeId, $name);

        if ($slug === 'firearm') {
            return str_replace('.firearm-module.', '.firearm.', $host);
        }

        return $host;
    }
}
