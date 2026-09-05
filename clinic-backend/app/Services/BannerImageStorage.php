<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BannerImageStorage
{
    public const RULES = 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=4000,max_height=4000';

    public function store(UploadedFile $file): string
    {
        $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
        abort_unless($image, 422, 'The image could not be read.');
        $ratio = min(1, 2000 / max(imagesx($image), imagesy($image)));
        if ($ratio < 1) {
            $resized = imagescale($image, (int) (imagesx($image) * $ratio), (int) (imagesy($image) * $ratio));
            imagedestroy($image);
            $image = $resized;
        }
        imagesavealpha($image, true);
        ob_start();
        imagewebp($image, null, 88);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $path = 'gallery/'.Str::uuid().'.webp';
        abort_unless(Storage::disk('public')->put($path, $bytes), 500, 'The image could not be saved.');

        return '/storage/'.$path;
    }
}
