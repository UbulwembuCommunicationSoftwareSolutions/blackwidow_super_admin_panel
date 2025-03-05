<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Exceptions\CouldNotLoadImage;
use Spatie\Image\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Spatie\Image\Manipulations;
use Imagick;


class ImageHelper
{
    /**
     * @throws CouldNotLoadImage
     */
    public static function generatePwaIcons($subscription, $imagePath)
    {
        // Resolve the image path
        $imagePath = Storage::disk('public')->path($imagePath);

        if (!file_exists($imagePath)) {
            throw new \Exception("Image file not found: " . $imagePath);
        }

        // Define storage paths
        $relativeBasePath = "pwa-icons/{$subscription->id}";
        $basePath = Storage::disk('public')->path($relativeBasePath);

        // Remove old icons
        exec("rm -rf {$basePath}/*");

        // Ensure the directory exists
        Storage::disk('public')->makeDirectory($relativeBasePath);

        // Define the temporary Quasar project directory
        $quasarProjectPath = '~/quasar-temp/icon-genie-project';

        // Run IconGenie inside the Quasar project
        $command = "cd {$quasarProjectPath} && icongenie generate -m pwa -i " . escapeshellarg($imagePath) . " -o " . escapeshellarg($basePath);
        \Log::info("Running command: " . $command);
        exec($command, $output, $returnVar);

        // Check if the command executed successfully
        if ($returnVar !== 0) {
            throw new \Exception("IconGenie failed to generate icons: " . implode("\n", $output));
        }
        $command = "cd {$quasarProjectPath} && icongenie generate -m pwa -i " . escapeshellarg($imagePath) . " --include pwa";
        $quasarIconsPath = "{$quasarProjectPath}/public";
        exec($command, $output, $returnVar);
        // Check if the command executed successfully
        if ($returnVar !== 0) {
            throw new \Exception("IconGenie failed to generate icons: " . implode("\n", $output));
        }

        // Move generated icons to Laravel storage
        exec("mv {$quasarIconsPath}/* " . escapeshellarg($basePath));

        return true;
    }

    /**
     * Generate Favicon ICO file from an image
     */
    private static function generateFaviconIco($imagePath, $outputPath)
    {
        $icoSizes = [48, 72, 96, 144, 192, 512]; // Standard PWA icon sizes

        $imagick = new Imagick();
        $imagick->setFormat('ico');
        $ico = new Imagick();
        $ico->setFormat("ico"); // Set format to ICO

        foreach ($icoSizes as $size) {
            $img = new Imagick($imagePath);
            $img->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, false);
            $outputPath = $outputPath . "icon-{$size}x{$size}.png";
            \Log::info("Saving image to: " . $outputPath);
            $img->writeImage($outputPath);
            $img->clear();
            $ico->addImage($img);
        }
        \Log::info("Saving image to: favicon.ico");
        $ico->writeImage($outputPath . "favicon.ico");

        $ico->clear();

        $imagick->writeImages($outputPath, true);
    }
}
