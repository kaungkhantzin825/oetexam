<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guarded — no-op when the table already exists (the normal case here). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exams')) {
            return;
        }

        Schema::create('exams', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->enum('section', ['Listening', 'Reading', 'Writing', 'Speaking', 'Full']);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->unsignedInteger('pass_marks')->default(0);
            $table->unsignedInteger('total_marks')->default(0);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('section', 'idx_exams_section');
            $table->index('is_published', 'idx_exams_published');
            $table->foreign('created_by', 'fk_exams_created_by')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
    }
};
