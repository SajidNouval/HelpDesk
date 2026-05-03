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
            $table->id();

            // Data user (guest)
            $table->string('name');
            $table->string('email');

            $table->string('subject');
            $table->text('message');

            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnDelete();

            // optional jika login
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // staff yang menangani
            $table->foreignId('staff_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('status', [
                'open',
                'assigned',
                'progress',
                'waiting',
                'closed'
            ])->default('open');

            $table->enum('priority', [
                'low',
                'medium',
                'high'
            ])->default('medium');

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('closed_at')->nullable();

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
