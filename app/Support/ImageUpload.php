<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Small helper for form-attached image uploads (e.g. a team logo), kept outside
 * the media library because these files belong to one record rather than the
 * shared Media table.
 *
 * Mirrors MediaController's storage mechanics on purpose: uploads land in
 * public/<dir> under a UUID filename and raster formats are re-encoded through
 * GD, which strips EXIF/GPS metadata and neutralises anything smuggled in image
 * headers. The caller is still responsible for validating `image|mimes:...`.
 */
class ImageUpload
{
    /**
     * Store an upload under public/<dir> and return its public path
     * (e.g. "/uploads/teams/<uuid>.png") for saving on the owning record.
     */
    public static function store(UploadedFile $file, string $dir): string
    {
        $dir = trim($dir, '/');
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $filename = Str::uuid() . '.' . $ext;

        $uploadDir = public_path($dir);
        if (! File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        // GIFs are moved as-is (GD would flatten the animation) — they carry no
        // EXIF risk. Everything else is re-encoded, with a plain move as the
        // fallback when GD isn't available for that format.
        if ($ext === 'gif' || ! self::reencode($file->getRealPath(), $destination, $ext)) {
            $file->move($uploadDir, $filename);
        }

        return '/' . $dir . '/' . $filename;
    }

    /**
     * Delete a previously stored image by its public path. Ignores anything that
     * isn't a local upload path so a stray external URL can't point File::delete
     * at an unrelated file.
     */
    public static function delete(?string $publicPath): void
    {
        if (! $publicPath || ! Str::startsWith($publicPath, '/')) {
            return;
        }

        $relative = ltrim($publicPath, '/');
        if (Str::contains($relative, '..')) {
            return;
        }

        File::delete(public_path($relative));
    }

    /**
     * Re-encode an image to its own format via GD, dropping all metadata.
     * Returns false if GD can't handle it (caller falls back to a plain move).
     */
    private static function reencode(string $src, string $dest, string $ext): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $data = @file_get_contents($src);
        if ($data === false) {
            return false;
        }

        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return false;
        }

        // Preserve transparency for formats that support it.
        imagealphablending($img, false);
        imagesavealpha($img, true);

        $ok = match ($ext) {
            'jpg', 'jpeg' => imagejpeg($img, $dest, 85),
            'png'         => imagepng($img, $dest, 6),
            'webp'        => function_exists('imagewebp') && imagewebp($img, $dest, 85),
            default       => false,
        };

        imagedestroy($img);

        return (bool) $ok;
    }
}
