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
        Schema::table('articles', function (Blueprint $table) {
            // Tambah FULLTEXT index untuk title dan content
            // Membutuhkan MySQL 5.7+ atau MariaDB 10.0+
            DB::statement('ALTER TABLE articles ADD FULLTEXT ft_title_content (title, content)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // Hapus FULLTEXT index
            DB::statement('ALTER TABLE articles DROP INDEX ft_title_content');
        });
    }
};
