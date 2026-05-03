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
        // Sqlite doesn't support modifying enum columns, so we need to recreate
        if (DB::getDriverName() === 'sqlite') {
            // For SQLite, we need to rebuild the table
            Schema::table('tickets', function (Blueprint $table) {
                // SQLite doesn't support modifying enums in place
                // We'll use raw SQL
            });

            // Use raw SQL to modify enum (SQLite doesn't have enums, uses CHECK constraint)
            DB::statement("
                CREATE TABLE tickets_new AS 
                SELECT * FROM tickets;
            ");

            DB::statement("DROP TABLE tickets;");

            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('subject');
                $table->text('message');
                $table->foreignId('category_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();
                $table->foreignId('staff_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->enum('status', [
                    'open',
                    'assigned',
                    'progress',
                    'waiting',
                    'suspended',
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

            DB::statement("
                INSERT INTO tickets 
                SELECT * FROM tickets_new;
            ");

            DB::statement("DROP TABLE tickets_new;");
        } else {
            // For MySQL and PostgreSQL, we can modify the enum
            Schema::table('tickets', function (Blueprint $table) {
                // Change the enum to include 'suspended'
                $table->enum('status', [
                    'open',
                    'assigned',
                    'progress',
                    'waiting',
                    'suspended',
                    'closed'
                ])->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // Reverse for SQLite
            DB::statement("
                CREATE TABLE tickets_new AS 
                SELECT * FROM tickets;
            ");

            DB::statement("DROP TABLE tickets;");

            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->string('subject');
                $table->text('message');
                $table->foreignId('category_id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();
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

            DB::statement("
                INSERT INTO tickets 
                SELECT * FROM tickets_new;
            ");

            DB::statement("DROP TABLE tickets_new;");
        } else {
            Schema::table('tickets', function (Blueprint $table) {
                $table->enum('status', [
                    'open',
                    'assigned',
                    'progress',
                    'waiting',
                    'closed'
                ])->change();
            });
        }
    }
};
