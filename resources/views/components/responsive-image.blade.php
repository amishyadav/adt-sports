@props(['src', 'alt' => '', 'eager' => false])
@php
    $src  = (string) $src;
    $webp = null;
    $w = null; $h = null;

    // For locally-uploaded images, prefer a WebP sibling and set intrinsic
    // dimensions (prevents layout shift / CLS).
    if (\Illuminate\Support\Str::startsWith($src, '/uploads/')) {
        $candidate = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
        if ($candidate !== $src && is_file(public_path(ltrim($candidate, '/')))) {
            $webp = $candidate;
        }

        $abs = public_path(ltrim($src, '/'));
        if (is_file($abs) && ($size = @getimagesize($abs))) {
            [$w, $h] = $size;
        }
    }
@endphp
<picture>
    @if($webp)<source srcset="{{ $webp }}" type="image/webp">@endif
    <img src="{{ $src }}" alt="{{ $alt }}"
         loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async"
         @if($eager) fetchpriority="high" @endif
         @if($w && $h) width="{{ $w }}" height="{{ $h }}" @endif
         {{ $attributes }}>
</picture>
