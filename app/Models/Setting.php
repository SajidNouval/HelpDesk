<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
 * - Menggunakan cache untuk mengurangi query DB yang berulang.
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
     * Durasi cache dalam detik (5 menit).
     */
    private const CACHE_TTL = 300;

    /**
     * Menghasilkan cache key untuk setting tertentu.
     */
    private static function cacheKey(string $key): string
    {
        return "setting:{$key}";
    }

    /**
     * Fungsi:
     * Mengambil nilai setting berdasarkan key.
     * Hasil di-cache selama 5 menit untuk mengurangi query DB.
     *
     * Output:
     * - String nilai setting atau default jika tidak ditemukan.
     */
    public static function getValue(string $key, $default = null)
    {
        return Cache::remember(
            self::cacheKey($key),
            self::CACHE_TTL,
            function () use ($key, $default) {
                $setting = static::where('key', $key)->first();
                return $setting ? $setting->value : $default;
            }
        );
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
     * Menyimpan nilai setting dan menghapus cache terkait.
     *
     * Output:
     * - Model Setting yang diupdate atau dibuat.
     */
    public static function setValue(string $key, $value)
    {
        // Hapus cache agar nilai baru langsung digunakan
        Cache::forget(self::cacheKey($key));

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
        );
    }
}

