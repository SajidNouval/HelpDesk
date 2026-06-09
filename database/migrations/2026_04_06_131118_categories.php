<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =========================================================================
 * MIGRATION CATEGORIES - TABEL KATEGORI
 * =========================================================================
 *
 * Migration ini membuat tabel categories untuk menyimpan kategori artikel.
 *
 * Kolom:
 * - id: ULID primary key
 * - name: Nama kategori
 * - description: Deskripsi kategori (nullable)
 * - timestamps: created_at dan updated_at
 */
return new class extends Migration
{
    /**
     * Fungsi:
     * Menjalankan migration untuk membuat tabel categories.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Fungsi:
     * Menghapus tabel categories.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
