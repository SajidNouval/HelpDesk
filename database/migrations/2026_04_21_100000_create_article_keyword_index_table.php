<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_keyword_index', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->onDelete('cascade');
            $table->string('keyword')->index();
            $table->float('tf')->default(0); // Term Frequency
            $table->json('field_boosts')->nullable(); // {'title': 3, 'content': 1}

            // Unique constraint untuk prevent duplikasi
            $table->unique(['article_id', 'keyword']);
        });

        // Create index untuk faster lookup
        Schema::table('article_keyword_index', function (Blueprint $table) {
            $table->index(['keyword', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_keyword_index');
    }
};
