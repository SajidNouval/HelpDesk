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
        Schema::table('article_feedback', function (Blueprint $table) {
            if (!Schema::hasColumn('article_feedback', 'ip_address')) {
                $table->string('ip_address', 45)->after('article_id');
            }
            $table->unique(['article_id', 'ip_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('article_feedback', function (Blueprint $table) {
            $table->dropUnique(['article_id', 'ip_address']);
            $table->dropColumn('ip_address');
        });
    }
};
