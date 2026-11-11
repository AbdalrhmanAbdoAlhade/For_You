<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileUploadService
{
    private const WEBP_QUALITY = 85;

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Upload image after converting it to optimized WebP.
     */
    public function upload(
        UploadedFile $file,
        string $folder = 'uploads'
    ): string {
        $image = $this->imageManager->read($file->getRealPath());

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
        if (!$path) {
            return;
        }

        $relativePath = str_replace('/storage/', '', $path);

        Storage::disk('public')->delete($relativePath);
    }
}
