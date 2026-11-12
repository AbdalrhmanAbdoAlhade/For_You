<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class FileUploadService
{
    private const WEBP_QUALITY = 85;

    private ?ImageManager $imageManager = null;

    /**
     * Lazily resolve the image manager so controllers can be constructed on
     * servers without the GD extension. The dependency is only required when
     * an image is actually uploaded.
     */
    private function imageManager(): ImageManager
    {
        if ($this->imageManager === null) {
            if (extension_loaded('gd')) {
                $this->imageManager = new ImageManager(new GdDriver());
            } elseif (extension_loaded('imagick')) {
                $this->imageManager = new ImageManager(new ImagickDriver());
            } else {
                throw new \RuntimeException(
                    'The GD or Imagick PHP extension is required to process image uploads.'
                );
            }
        }

        return $this->imageManager;
    }

    /**
     * Upload image after converting it to optimized WebP.
     */
    public function upload(
        UploadedFile $file,
        string $folder = 'uploads'
    ): string {
        $image = $this->imageManager()->read($file->getRealPath());

        // Preserve original dimensions and aspect ratio.
        $encoded = $image->toWebp(self::WEBP_QUALITY);

        $filename = Str::uuid() . '.webp';

        $path = trim($folder, '/') . '/' . $filename;

        $stored = Storage::disk('public')->put(
            $path,
            (string) $encoded
        );

        if (!$stored) {
            throw new \RuntimeException(
                'Failed to store the converted image.'
            );
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Upload multiple images.
     */
    public function uploadMultiple(
        array $files,
        string $folder = 'uploads'
    ): array {
        $paths = [];

        try {
            foreach ($files as $file) {
                $paths[] = $this->upload($file, $folder);
            }

            return $paths;
        } catch (\Throwable $exception) {
            // Clean up files uploaded before the failure.
            foreach ($paths as $path) {
                $this->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * Delete an uploaded file.
     */
  public function delete(?string $path): void
{
    if (!$path) return;

    $relative = parse_url($path, PHP_URL_PATH) ?: $path;
    $relative = ltrim(preg_replace('#^/?storage/#', '', $relative), '/');

    Storage::disk('public')->delete($relative);
}
}
