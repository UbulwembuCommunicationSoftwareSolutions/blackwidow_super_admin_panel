<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Imagick;

class ImageHelper
{
    /**
     * @throws CouldNotLoadImage
     */
    public static function generatePwaIcons($subscription, $imagePath)
    {
        // Define required PWA icons with sizes and filenames
        $icons = [
            ['size' => 192, 'filename' => 'android-chrome-192x192.png'],
            ['size' => 512, 'filename' => 'android-chrome-512x512.png'],
            ['size' => 192, 'filename' => 'android-chrome-maskable-192x192.png'],
            ['size' => 512, 'filename' => 'android-chrome-maskable-512x512.png'],
            ['size' => 180, 'filename' => 'apple-touch-icon.png'],
            ['size' => 16,  'filename' => 'favicon-16x16.png'],
            ['size' => 32,  'filename' => 'favicon-32x32.png'],
        ];

        // Resolve the image path
        $imagePath = Storage::disk('public')->path($imagePath);

        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: " . $imagePath);
        }

        // Define the storage path
        $relativeBasePath = "pwa-icons/{$subscription->id}";
        $basePath = Storage::disk('public')->path($relativeBasePath);
        exec('rm -rf ' . $basePath.'/*');
        Storage::makeDirectory($basePath);
        foreach ($icons as $icon) {
            $relativeOutputPath = "{$relativeBasePath}/{$icon['filename']}";
            $outputPath = Storage::disk('public')->path($relativeOutputPath);

            \Log::info("Saving image to: " . $outputPath);

            // Resize and save the image using Spatie Image
            Image::load($imagePath)
                ->width($icon['size'])
                ->height($icon['size'])
                ->save($outputPath);

            // Optimize the image
            $optimizerChain = OptimizerChainFactory::create();
            $optimizerChain->optimize($outputPath);

            if (!file_exists($outputPath)) {
                throw new \Exception("Image was not saved at: " . $outputPath);
            }
        }

        // Generate Favicon.ico
        self::generateFaviconIco($imagePath, "{$basePath}/favicon.ico");

        return true;
    }

    /**
     * Generate Favicon ICO file from an image
     */
    private static function generateFaviconIco($imagePath, $outputPath)
    {
        $icoSizes = [16, 32, 48, 64];

        $imagick = new Imagick();
        $imagick->setFormat('ico');

        foreach ($icoSizes as $size) {
            $img = new Imagick($imagePath);
            $img->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
            $imagick->addImage($img);
        }

        $imagick->writeImages($outputPath, true);
    }
}
