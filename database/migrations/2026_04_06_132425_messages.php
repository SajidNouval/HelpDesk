<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
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
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
