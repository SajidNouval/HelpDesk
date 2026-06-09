<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * =========================================================================
 * MODEL SETTING
 * =========================================================================
 *
 * Model ini merepresentasikan tabel settings.
 *
 * Tanggung Jawab:
 * - Menyimpan pengaturan sistem.
 * - Menyediakan helper method untuk get/set setting.
 * - Menangani konversi boolean untuk setting.
 */
class Setting extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'settings';
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Fungsi:
     * Mengambil nilai setting berdasarkan key.
     *
     * Output:
     * - String nilai setting atau default jika tidak ditemukan.
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Fungsi:
     * Mengambil nilai setting dan mengkonversi ke boolean.
     *
     * Output:
     * - Boolean nilai setting.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key, $default);
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Fungsi:
     * Menyimpan nilai setting.
     *
     * Output:
     * - Model Setting yang diupdate atau dibuat.
     */
    public static function setValue(string $key, $value)
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
        );
    }
}
