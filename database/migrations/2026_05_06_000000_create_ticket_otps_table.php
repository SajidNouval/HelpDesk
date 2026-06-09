<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =========================================================================
 * MIGRATION TICKET OTPS - TABEL OTP TIKET
 * =========================================================================
 *
 * Migration ini membuat tabel ticket_otps untuk menyimpan data OTP verifikasi tiket.
 *
 * Kolom:
 * - id: ULID primary key
 * - name: Nama pengirim
 * - email: Email pengirim
 * - subject: Subjek tiket
 * - message: Pesan tiket
 * - category_id: Foreign key ke tabel categories
 * - type: Tipe tiket (livechat, report)
 * - otp_code: Kode OTP 6 digit
 * - attempts: Jumlah percobaan OTP
 * - expires_at: Timestamp kedaluwarsa OTP
 * - token: Token unik untuk tracking
 * - timestamps: created_at dan updated_at
 */
return new class extends Migration
{
    /**
     * Fungsi:
     * Menjalankan migration untuk membuat tabel ticket_otps.
     */
    public function up(): void
    {
        Schema::create('ticket_otps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->foreignUlid('category_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->enum('type', ['livechat', 'report'])->default('livechat');
            $table->string('otp_code', 6);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->string('token', 80)->unique();
            $table->timestamps();
        });
    }

    /**
     * Fungsi:
     * Menghapus tabel ticket_otps.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_otps');
    }
};
