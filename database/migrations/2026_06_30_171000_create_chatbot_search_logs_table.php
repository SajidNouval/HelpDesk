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
        Schema::create('chatbot_search_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            // Query details
            $table->string('query_original', 1000);
            $table->string('query_normalized', 1000)->nullable();
            
            // Domain and score metadata
            $table->string('detected_domain')->nullable();
            $table->float('confidence')->nullable();
            $table->integer('results_count')->default(0);
            
            // Top matched article recommendations
            $table->foreignUlid('top_result_id')
                ->nullable()
                ->constrained('articles')
                ->nullOnDelete();
            $table->string('top_result_title')->nullable();
            $table->float('top_result_score')->nullable();
            
            // Status flags
            $table->boolean('is_fallback_triggered')->default(false);
            
            // Client details
            $table->string('ip_address', 45)->nullable();
            $table->foreignUlid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
                
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_search_logs');
    }
};
