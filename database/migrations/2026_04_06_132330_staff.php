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
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->boolean('is_busy')->default(false);

            $table->timestamps();

            // Indexes
            $table->index(['category_id', 'is_busy'], 'staff_profiles_category_busy_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
