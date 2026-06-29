<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('category_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignUlid('staff_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title', 200);
            $table->string('slug', 255)->unique();
            $table->longText('content');
            $table->text('excerpt')->nullable();
            $table->string('keywords', 500)->nullable();

            $table->integer('views')->default(0);

            $table->boolean('is_published')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->enum('publish_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_note')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['publish_status', 'is_published'], 'articles_publish_status_published_idx');
            $table->index('views', 'articles_views_idx');
            $table->index('staff_id', 'articles_staff_idx');
            $table->index('category_id', 'articles_category_idx');
        });

        // Add FULLTEXT index untuk MySQL 5.7+ atau MariaDB 10.0+
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement('ALTER TABLE articles ADD FULLTEXT ft_title_content (title, content)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop FULLTEXT index jika ada
        if ((DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') && 
            Schema::hasTable('articles')) {
            try {
                DB::statement('ALTER TABLE articles DROP INDEX ft_title_content');
            } catch (\Exception $e) {
                // Index might not exist
            }
        }

        Schema::dropIfExists('articles');
    }
};
