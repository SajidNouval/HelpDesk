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
        Schema::create('tickets', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Data user (guest)
            $table->string('name');
            $table->string('email');

            $table->string('subject');
            $table->text('message');

            $table->foreignUlid('category_id')
                ->constrained()
                ->cascadeOnDelete();

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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
