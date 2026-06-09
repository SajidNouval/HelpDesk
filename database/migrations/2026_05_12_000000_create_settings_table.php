<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =========================================================================
 * MIGRATION SETTINGS - TABEL PENGATURAN
 * =========================================================================
 *
 * Migration ini membuat tabel settings untuk menyimpan pengaturan sistem.
 *
 * Kolom:
 * - id: ULID primary key
 * - key: Kunci pengaturan (unique)
 * - value: Nilai pengaturan (nullable)
 * - timestamps: created_at dan updated_at
 */
return new class extends Migration
{
    /**
     * Fungsi:
     * Menjalankan migration untuk membuat tabel settings.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Fungsi:
     * Menghapus tabel settings.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
