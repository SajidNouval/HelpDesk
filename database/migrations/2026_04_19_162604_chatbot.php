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
        Schema::create('chatbot', function (Blueprint $table) {
            $table->id();

            // keyword bisa lebih dari 1 (pisah koma)
            $table->string('keywords');
            // contoh: "wifi,internet,lemot"

            // jawaban chatbot
            $table->text('response');

            // optional: terkait kategori tiket
            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // status aktif / nonaktif
            $table->boolean('is_active')->default(true);

            // untuk prioritas jika keyword mirip
            $table->integer('priority')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot');
    }
};
