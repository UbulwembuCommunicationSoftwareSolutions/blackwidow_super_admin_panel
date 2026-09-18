<?php

namespace App\Helpers;

use App\Models\CustomerSubscription;
use Exception;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

class ImageHelper
{
    /**
     * @var array<string, int>
     */
    private const STANDARD_ICON_SIZES = [
        'favicon-16x16.png' => 16,
        'favicon-32x32.png' => 32,
        'favicon-96x96.png' => 96,
        'apple-icon-120x120.png' => 120,
        'icon-128x128.png' => 128,
        'ms-icon-144x144.png' => 144,
        'apple-icon-152x152.png' => 152,
        'apple-icon-167x167.png' => 167,
        'apple-icon-180x180.png' => 180,
        'icon-192x192.png' => 192,
        'icon-256x256.png' => 256,
        'icon-384x384.png' => 384,
        'icon-512x512.png' => 512,
    ];

    /**
     * @var array<string, int>
     */
    private const MASKABLE_ICON_SIZES = [
        'icon-192x192-maskable.png' => 192,
        'icon-512x512-maskable.png' => 512,
    ];

    /**
     * @return list<string>
     *
     * @throws Exception
     */
    public static function generatePwaIcons(CustomerSubscription $subscription, ?string $imagePath): array
    {
        if (blank($imagePath)) {
            throw new Exception('Logo path is blank.');
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($imagePath)) {
            throw new Exception('Image file not found: '.$imagePath);
        }

        $absoluteLogoPath = $disk->path($imagePath);

        if (! file_exists($absoluteLogoPath)) {
            throw new Exception('Image file not found: '.$imagePath);
        }

        $iconsDirectory = "pwa-icons/{$subscription->id}/icons";
        $disk->deleteDirectory($iconsDirectory);
        $disk->makeDirectory($iconsDirectory);

        $manager = self::imageManager();
        $generated = [];

        try {
            foreach (self::STANDARD_ICON_SIZES as $filename => $size) {
                $icon = self::containedIcon($manager, $absoluteLogoPath, $size, 'transparent');
                $relativePath = $iconsDirectory.'/'.$filename;
                $icon->toPng()->save($disk->path($relativePath));
                $generated[] = $relativePath;
            }

            foreach (self::MASKABLE_ICON_SIZES as $filename => $size) {
                $innerSize = (int) round($size * 0.8);
                $fitted = $manager->read($absoluteLogoPath)->contain($innerSize, $innerSize, 'ffffff');
                $maskable = $manager->create($size, $size)->fill('ffffff')->place($fitted, 'center');
                $relativePath = $iconsDirectory.'/'.$filename;
                $maskable->toPng()->save($disk->path($relativePath));
                $generated[] = $relativePath;
            }

            if (extension_loaded('imagick')) {
                $faviconPath = $iconsDirectory.'/favicon.ico';
                self::writeFaviconIco(
                    $disk->path($iconsDirectory.'/icon-512x512.png'),
                    $disk->path($faviconPath)
                );
                $generated[] = $faviconPath;
            }
        } catch (Throwable $exception) {
            throw new Exception('Failed to generate PWA icons: '.$exception->getMessage(), previous: $exception);
        }

        return $generated;
    }

    private static function imageManager(): ImageManager
    {
        if (extension_loaded('imagick')) {
            return ImageManager::imagick();
        }

        return ImageManager::gd();
    }

    private static function containedIcon(ImageManager $manager, string $absoluteLogoPath, int $size, string $background): ImageInterface
    {
        try {
            return $manager->read($absoluteLogoPath)->contain($size, $size, $background);
        } catch (Throwable) {
            $fitted = $manager->read($absoluteLogoPath)->scale($size, $size);
            $canvas = $manager->create($size, $size);

            if ($background !== 'transparent') {
                $canvas->fill($background);
            }

            return $canvas->place($fitted, 'center');
        }
    }

    private static function writeFaviconIco(string $sourcePath, string $outputPath): void
    {
        $source = new Imagick($sourcePath);
        $ico = new Imagick;
        $ico->setFormat('ico');

        foreach ([16, 32, 48] as $size) {
            $frame = clone $source;
            $frame->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1, true);
            $frame->setImageFormat('png');
            $ico->addImage($frame);
            $frame->clear();
            $frame->destroy();
        }

        $ico->writeImages($outputPath, true);
        $ico->clear();
        $ico->destroy();
        $source->clear();
        $source->destroy();
    }
}
