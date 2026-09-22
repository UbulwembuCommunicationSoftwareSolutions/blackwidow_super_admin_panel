<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionTypeRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_type_id',
        'tag',
        'commit_sha',
        'name',
        'body',
        'is_prerelease',
        'is_draft',
        'requires_manual_rollback',
        'github_release_id',
        'published_at',
        'synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_prerelease' => 'boolean',
            'is_draft' => 'boolean',
            'requires_manual_rollback' => 'boolean',
            'published_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function subscriptionType(): BelongsTo
    {
        return $this->belongsTo(SubscriptionType::class);
    }

    public function pinnedSubscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class, 'pinned_release_id');
    }

    public function deployedSubscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class, 'deployed_release_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_draft', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeStable(Builder $query): Builder
    {
        return $query->published()->where('is_prerelease', false);
    }

    /**
     * Sort key for semver-ish tags (v1.2.3 / v1.2.3-rc.1). Falls back to the raw tag.
     */
    public function semverSortKey(): string
    {
        $tag = ltrim((string) $this->tag, 'vV');

        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-(.+))?$/', $tag, $matches)) {
            return $this->tag ?? '';
        }

        $prerelease = $matches[4] ?? 'zzzzzzzz';

        return sprintf(
            '%05d.%05d.%05d.%s',
            (int) $matches[1],
            (int) $matches[2],
            (int) $matches[3],
            $prerelease
        );
    }
}
