<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, $default = null)
    {
        $value = static::where('key', $key)->value('value');
        return $value ?? $default;
    }

    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    public static function getFirstValue(array $keys, $default = null)
    {
        foreach ($keys as $key) {
            $value = static::where('key', $key)->value('value');
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }
}
