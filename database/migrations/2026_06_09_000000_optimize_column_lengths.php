<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * =========================================================================
 * MIGRATION OPTIMIZE COLUMN LENGTHS
 * =========================================================================
 *
 * Migration ini mengoptimalkan panjang kolom di seluruh database berdasarkan
 * rekomendasi best practice dan kebutuhan sistem.
 *
 * Perubahan:
 * - name/email: 50 karakter (sesuai request user)
 * - password: 255 karakter (hash bcrypt 60 karakter, 255 lebih aman)
 * - subject/title: 200 karakter (cukup untuk judul/subjek)
 * - keywords: 500 karakter (bisa banyak keyword dipisah koma)
 * - slug: 255 karakter (perlu lebih panjang karena karakter khusus)
 * - content/response: longText (sangat panjang, hingga 4GB)
 * - message/description: text (panjang, hingga 65KB)
 * - action/key: 50-100 karakter (pendek)
 * - value: longText (bisa JSON/konfigurasi panjang)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 50)->change();
            $table->string('email', 50)->change();
            $table->string('password', 255)->change();
        });

        // Tickets table
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('name', 50)->change();
            $table->string('email', 50)->change();
            $table->string('subject', 200)->change();
        });

        // Articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->string('title', 200)->change();
            $table->string('slug', 255)->change();
            $table->longText('content')->change();
            $table->string('keywords', 500)->nullable()->change();
        });

        // Categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name', 100)->change();
        });

        // Chatbot table
        Schema::table('chatbot', function (Blueprint $table) {
            $table->string('keywords', 500)->change();
            $table->longText('response')->change();
        });

        // Ticket logs table
        Schema::table('ticket_logs', function (Blueprint $table) {
            $table->string('action', 50)->change();
        });

        // Notifications table
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title', 200)->change();
        });

        // Ticket OTPs table
        Schema::table('ticket_otps', function (Blueprint $table) {
            $table->string('name', 50)->change();
            $table->string('email', 50)->change();
            $table->string('subject', 200)->change();
        });

        // Settings table
        Schema::table('settings', function (Blueprint $table) {
            $table->string('key', 100)->change();
            $table->longText('value')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('email')->change();
            $table->string('password')->change();
        });

        // Tickets table
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('email')->change();
            $table->string('subject')->change();
        });

        // Articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->string('title')->change();
            $table->string('slug')->change();
            $table->longText('content')->change();
            $table->string('keywords')->nullable()->change();
        });

        // Categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->change();
        });

        // Chatbot table
        Schema::table('chatbot', function (Blueprint $table) {
            $table->string('keywords')->change();
            $table->text('response')->change();
        });

        // Ticket logs table
        Schema::table('ticket_logs', function (Blueprint $table) {
            $table->string('action')->change();
        });

        // Notifications table
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->change();
        });

        // Ticket OTPs table
        Schema::table('ticket_otps', function (Blueprint $table) {
            $table->string('name')->change();
            $table->string('email')->change();
            $table->string('subject')->change();
        });

        // Settings table
        Schema::table('settings', function (Blueprint $table) {
            $table->string('key')->change();
            $table->text('value')->change();
        });
    }
};
