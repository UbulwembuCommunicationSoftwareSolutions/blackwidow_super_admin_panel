<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class ImageHelper
{
    public static function generatePwaIcons($customerId, $imagePath)
    {
        // Define the required PWA icon sizes
        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

        // Define the storage path for PWA icons
        $basePath = "public/pwa-icons/{$customerId}";
        Storage::makeDirectory($basePath);

        foreach ($sizes as $size) {
            $outputPath = "{$basePath}/icon-{$size}x{$size}.png";

            // Resize and save the image
            Image::load($imagePath)
                ->width($size)
                ->height($size)
                ->save(Storage::path($outputPath));

            // Optimize the image
            $optimizerChain = OptimizerChainFactory::create();
            $optimizerChain->optimize(Storage::path($outputPath));
        }

        return true;
    }
}
