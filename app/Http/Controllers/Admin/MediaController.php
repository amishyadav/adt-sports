<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, File};
use Illuminate\Support\Str;

class MediaController extends Controller
{
    /** Single source of truth for where uploads live and how the disk is labelled. */
    private const DISK  = 'public';
    private const DIR   = 'uploads';

    public function index()
    {
        $media = Media::with('uploader')->latest()->paginate(40);
        return view('admin.media.index', compact('media'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:10240',
            'alt'  => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $ext  = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid() . '.' . $ext;

        $uploadDir = public_path(self::DIR);
        if (! File::exists($uploadDir)) {
            File::makeDirectory($uploadDir, 0755, true);
        }
        $destination = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        // Re-encode raster images through GD to strip EXIF/GPS metadata and
        // neutralise any payload smuggled in image headers. Animated GIFs are
        // moved as-is (GD would flatten them) — they carry no EXIF risk.
        if ($ext === 'gif' || ! $this->reencode($file->getRealPath(), $destination, $ext)) {
            $file->move($uploadDir, $filename);
        }

        // Generate a WebP sibling so <x-responsive-image> can serve a smaller format.
        $this->generateWebp($destination, $ext);

        $media = Media::create([
            'filename'      => $filename,
            'original_name' => $file->getClientOriginalName(),
            'alt'           => $request->input('alt'),
            'mimetype'      => $file->getMimeType(),
            'size'          => @filesize($destination) ?: $file->getSize(),
            'url'           => '/' . self::DIR . '/' . $filename,
            'disk'          => self::DISK,
            'uploaded_by'   => Auth::id(),
        ]);

        return response()->json([
            'id'   => $media->id,
            'url'  => $media->url,
            'name' => $media->original_name,
            'alt'  => $media->alt,
        ]);
    }

    /** Update the alt text of an existing image (from the media library). */
    public function update(Request $request, Media $media)
    {
        $data = $request->validate(['alt' => 'nullable|string|max:255']);
        $media->update(['alt' => $data['alt'] ?? null]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Media $media)
    {
        // All uploads live under public/uploads regardless of legacy disk labels.
        File::delete(public_path(self::DIR . '/' . $media->filename));
        $media->delete();

        return back()->with('success', 'Image deleted.');
    }

    /**
     * Re-encode an image to its own format via GD, dropping all metadata.
     * Returns false if GD can't handle it (caller falls back to a plain move).
     */
    private function reencode(string $src, string $dest, string $ext): bool
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

        return $ok;
    }

    /** Write a .webp sibling next to a jpg/png upload (smaller payload for modern browsers). */
    private function generateWebp(string $path, string $ext): void
    {
        if (! function_exists('imagewebp') || ! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return;
        }

        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return;
        }

        imagepalettetotruecolor($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);

        $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
        @imagewebp($img, $webpPath, 82);

        imagedestroy($img);
    }
}
