<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores an uploaded image on the public disk after re-encoding it with GD.
 * Re-encoding drops EXIF/metadata (GPS location, phone model, …) that phone photos carry,
 * which matters because these files are publicly reachable.
 */
class PublicImage
{
    public static function store(UploadedFile $file, string $dir): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($image === false) {
            return $file->store($dir, 'public'); // not decodable by GD; validation already ensured it's an image
        }

        $png = $file->getMimeType() === 'image/png'; // keep PNG for transparency (logos)
        $path = $dir.'/'.Str::random(40).($png ? '.png' : '.jpg');

        ob_start();
        if ($png) {
            imagesavealpha($image, true);
            imagepng($image, null, 6);
        } else {
            imagejpeg($image, null, 85);
        }
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, ob_get_clean());
        imagedestroy($image);

        return $path;
    }
}
