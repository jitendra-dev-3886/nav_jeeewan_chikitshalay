<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandingController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=4000,max_height=4000']);
        $image = imagecreatefromstring(file_get_contents($request->file('image')->getRealPath()));
        abort_unless($image, 422, 'This image could not be read.');
        $ratio = min(1, 800 / max(imagesx($image), imagesy($image)));
        if ($ratio < 1) {
            $scaled = imagescale($image, (int) (imagesx($image) * $ratio), (int) (imagesy($image) * $ratio));
            imagedestroy($image);
            $image = $scaled;
        }
        imagesavealpha($image, true);
        ob_start();
        imagewebp($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $path = 'gallery/'.Str::uuid().'.webp';
        abort_unless(Storage::disk('public')->put($path, $bytes), 500, 'Unable to save the logo.');
        $branding = ['logo_url' => '/storage/'.$path, 'custom' => true];
        DB::transaction(function () use ($branding) {
            Setting::updateOrCreate(['key' => 'branding'], ['value' => $branding]);
            AuditLog::record('branding.logo.updated', 'settings');
        });

        return response()->json($branding);
    }

    public function reset()
    {
        $branding = ['logo_url' => '/brand-logo.jpg', 'custom' => false];
        DB::transaction(function () use ($branding) {
            Setting::updateOrCreate(['key' => 'branding'], ['value' => $branding]);
            AuditLog::record('branding.logo.restored', 'settings');
        });

        return response()->json($branding);
    }
}
