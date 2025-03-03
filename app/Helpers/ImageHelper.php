<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class ImageHelper
{
    /**
     * @throws CouldNotLoadImage
     */
    public static function generatePwaIcons($subscription, $imagePath)
    {
        // Define the required PWA icon sizes
        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

        $imagePath = Storage::disk('public')->path($imagePath);
        // Define the storage path for PWA icons

        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: " . $imagePath);
        }


        $basePath = Storage::disk('public')->path('pwa-icons/'.$subscription->id);
        $basePath = "pwa-icons/{$subscription->id}";
        Storage::disk('public')->makeDirectory($basePath); // Ensure directory exists
        Storage::makeDirectory($basePath);


        foreach ($sizes as $size) {
            $outputPath = "{$basePath}/icon-{$size}x{$size}.png";
            $storagePath = Storage::path($outputPath);
            if (!is_writable(dirname($storagePath))) {
                throw new \Exception("Directory not writable: " . dirname($storagePath));
            }

            \Log::error("Saving image to: " . Storage::path($outputPath));
            // Resize and save the image
            Image::load($imagePath)
                ->width($size)
                ->height($size)
                ->save($outputPath);

            // Optimize the image
            $optimizerChain = OptimizerChainFactory::create();
            $optimizerChain->optimize(Storage::path($outputPath));
            if (!Storage::exists($outputPath)) {
                throw new \Exception("Image was not saved.");
            }
        }

        return true;
    }
}
