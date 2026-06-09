<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =========================================================================
 * MIGRATION MESSAGES - TABEL PESAN
 * =========================================================================
 *
 * Migration ini membuat tabel messages untuk menyimpan pesan dalam tiket.
 *
 * Kolom:
 * - id: ULID primary key
 * - ticket_id: Foreign key ke tabel tickets
 * - sender_type: Tipe pengirim (guest, staff)
 * - sender_id: Foreign key ke tabel users untuk staff (nullable)
 * - message: Isi pesan
 * - is_read: Status pesan sudah dibaca atau belum
 * - timestamps: created_at dan updated_at
 */
return new class extends Migration
{
    /**
     * Fungsi:
     * Menjalankan migration untuk membuat tabel messages.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('ticket_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('sender_type', ['guest', 'staff']);

            // kalau staff kirim
            $table->foreignUlid('sender_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('message');

            $table->boolean('is_read')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Fungsi:
     * Menghapus tabel messages.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
