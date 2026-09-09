<?php

namespace App\Models;

use App\Services\CustomerSubscriptionService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionType extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = [
        'name',
        'github_repo',
        'project_type',
        'public_dir',
        'branch',
        'master_version'
    ];

    protected $appends = ['logo_descriptions'];

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
}
