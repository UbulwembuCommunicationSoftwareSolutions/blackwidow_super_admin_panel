<?php

namespace App\Models;

use App\Support\BrandingSync\BrandingSyncPayload;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CustomerBrandingMedia extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'name',
        'checksum',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file')->singleFile();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function url(): ?string
    {
        $media = $this->getFirstMedia('file');
        if ($media === null) {
            return null;
        }

        $fullUrl = $media->getFullUrl();

        return filled($fullUrl) ? $fullUrl : $media->getUrl();
    }

    public function refreshChecksumFromFile(): void
    {
        $media = $this->getFirstMedia('file');
        if ($media === null) {
            $this->forceFill(['checksum' => null])->save();

            return;
        }

        $path = $media->getPath();
        if (! is_readable($path)) {
            return;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }

        $this->forceFill([
            'checksum' => BrandingSyncPayload::computeChecksum($contents),
        ])->save();
    }
}
