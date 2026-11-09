<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    public function upload(UploadedFile $file, string $folder = 'uploads'): string
    {
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, 'public');

        return Storage::url($path); // يرجع /storage/...
    }

    public function uploadMultiple(array $files, string $folder = 'uploads'): array
    {
        $paths = [];
        foreach ($files as $file) {
            $paths[] = $this->upload($file, $folder);
        }
        return $paths;
    }

    public function delete(?string $path): void
    {
        if (!$path) return;

        $relativePath = str_replace('/storage/', '', $path);
        Storage::disk('public')->delete($relativePath);
    }
}
