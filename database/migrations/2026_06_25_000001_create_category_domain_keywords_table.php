<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =========================================================================
 * MIGRATION CATEGORY DOMAIN KEYWORDS
 * =========================================================================
 *
 * Migration ini membuat tabel category_domain_keywords untuk menyimpan
 * kata kunci domain per kategori, sehingga domain detection chatbot
 * dapat membaca mapping secara dinamis dari database.
 *
 * Kolom:
 * - id          : ULID primary key
 * - category_id : Foreign key ke tabel categories (cascade delete)
 * - keyword     : Kata kunci yang memicu deteksi domain ini
 * - timestamps  : created_at dan updated_at
 *
 * Relasi:
 * - Satu kategori dapat memiliki banyak keyword (one-to-many)
 */
return new class extends Migration
{
    /**
     * Fungsi:
     * Membuat tabel category_domain_keywords.
     */
    public function up(): void
    {
        Schema::create('category_domain_keywords', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('category_id');
            $table->string('keyword', 100);

            $table->foreign('category_id')
                  ->references('id')
                  ->on('categories')
                  ->onDelete('cascade');

            // Index untuk mempercepat pencarian keyword per kategori
            $table->index('category_id');
            // Index unik agar tidak ada keyword duplikat per kategori
            $table->unique(['category_id', 'keyword']);

            $table->timestamps();
        });
    }

    /**
     * Fungsi:
     * Menghapus tabel category_domain_keywords.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_domain_keywords');
    }
};
