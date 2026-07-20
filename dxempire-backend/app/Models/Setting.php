<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public $timestamps = false;

    protected $fillable = ['key', 'value', 'updated_at'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember("setting:{$key}", 300, function () use ($key) {
            return static::where('key', $key)->value('value');
        });

        return $value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $encoded = is_array($value) ? json_encode($value) : $value;

        static::updateOrCreate(['key' => $key], ['value' => $encoded, 'updated_at' => now()]);

        Cache::forget("setting:{$key}");
    }

    public static function getJson(string $key, mixed $default = []): mixed
    {
        $raw = static::get($key);

        return $raw ? json_decode($raw, true) : $default;
    }
}
