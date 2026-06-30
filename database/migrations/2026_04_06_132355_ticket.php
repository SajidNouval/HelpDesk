<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =========================================================================
 * MIGRATION TICKET - TABEL TIKET
 * =========================================================================
 *
 * Migration ini membuat tabel tickets untuk menyimpan tiket helpdesk.
 *
 * Kolom:
 * - id: ULID primary key
 * - name: Nama pengirim (guest)
 * - email: Email pengirim
 * - subject: Subjek tiket
 * - message: Pesan tiket
 * - category_id: Foreign key ke tabel categories
 * - type: Tipe tiket (livechat, report)
 * - user_id: Foreign key ke tabel users (nullable)
 * - staff_id: Foreign key ke tabel users untuk staff yang menangani (nullable)
 * - status: Status tiket (open, assigned, progress, waiting, closed, suspended)
 * - priority: Priority tiket (low, medium, high)
 * - assigned_at: Timestamp saat tiket ditugaskan (nullable)
 * - closed_at: Timestamp saat tiket ditutup (nullable)
 * - email_verified_at: Timestamp saat email diverifikasi (nullable)
 * - tracking_token: Token untuk tracking tiket (unique, nullable)
 * - timestamps: created_at dan updated_at
 */
return new class extends Migration
{
    /**
     * Fungsi:
     * Menjalankan migration untuk membuat tabel tickets.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Data user (guest)
            $table->string('name', 50);
            $table->string('email', 50);

            $table->string('subject', 200);
            $table->string('message', 2000);

            $table->foreignUlid('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', ['livechat', 'report'])->default('livechat');

            // optional jika login
            $table->foreignUlid('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // staff yang menangani
            $table->foreignUlid('staff_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', [
                'open',
                'assigned',
                'progress',
                'waiting',
                'closed',
                'suspended'
            ])->default('open');

            $table->enum('priority', [
                'low',
                'medium',
                'high'
            ])->default('medium');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('tracking_token', 80)->nullable()->unique();

            $table->timestamps();

            // Indexes
            $table->index('status', 'tickets_status_idx');
            $table->index(['staff_id', 'status'], 'tickets_staff_status_idx');
            $table->index('email', 'tickets_email_idx');
            $table->index('category_id', 'tickets_category_idx');
            $table->index('created_at', 'tickets_created_at_idx');
        });
    }

    /**
     * Fungsi:
     * Menghapus tabel tickets.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
