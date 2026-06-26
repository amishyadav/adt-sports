<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /** Slug is the public route key: /tag/{slug}. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function articles()
    {
        return $this->belongsToMany(Article::class);
    }

    /**
     * Resolve (or create) the canonical tag for a free-text name. Whitespace is
     * collapsed and the slug is the uniqueness key, so "PKL", "pkl", and
     * " P K L " all map to the same tag row.
     */
    public static function fromName(string $name): ?self
    {
        // Collapse whitespace and cap length so an over-long paste can't blow the
        // VARCHAR(255) column (a hard 500 under MySQL strict mode).
        $name = Str::limit(trim(preg_replace('/\s+/', ' ', $name)), 50, '');
        $slug = Str::slug($name);

        if ($name === '' || $slug === '') {
            return null;
        }

        try {
            return static::firstOrCreate(['slug' => $slug], ['name' => $name]);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent save created the same tag between our read and insert.
            return static::where('slug', $slug)->first();
        }
    }
}
