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
        Schema::dropIfExists('article_keyword_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('article_keyword_index', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('article_id')->constrained('articles')->onDelete('cascade');
            $table->string('keyword')->index();
            $table->float('tf')->default(0);
            $table->json('field_boosts')->nullable();
            $table->unique(['article_id', 'keyword']);
            $table->index(['keyword', 'article_id']);
        });
    }
};
