<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\ResponseCache\Facades\ResponseCache;

class Setting extends Model
{
    protected $fillable  = ['key','value','type','group'];
    public $incrementing = false;
    protected $primaryKey = 'key';
    public $keyType      = 'string';

    public const CACHE_KEY = 'settings.all';

    public static function get(string $key, $default = null): mixed
    {
        return static::allAsArray()[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
        ResponseCache::clear(); // settings appear on every page
    }

    /** Cached so the per-request settings lookup doesn't hit the DB every page load. */
    public static function allAsArray(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::pluck('value', 'key')->toArray());
    }
}
