<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guarded — no-op when the table already exists (the normal case here). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lessons')) {
            return;
        }

        Schema::create('lessons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug', 160)->comment('URL-safe unique identifier');
            $table->enum('category', ['Listening', 'Reading', 'Writing', 'Speaking', 'Grammar', 'Vocabulary', 'ExamStrategy'])
                ->default('ExamStrategy');
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner');
            $table->string('title', 200);
            $table->string('title_my', 255)->nullable()->comment('Myanmar title');
            $table->text('summary')->nullable()->comment('Short English teaser shown in the lesson list');
            $table->text('summary_my')->nullable()->comment('Myanmar teaser');
            $table->mediumText('content')->comment('Full English lesson body (Markdown)');
            $table->mediumText('content_my')->nullable()->comment('Full Myanmar lesson body (Markdown)');
            $table->json('key_points')->nullable()->comment('["takeaway", ...]');
            $table->json('key_points_my')->nullable()->comment('["အဓိကအချက်", ...]');
            $table->unsignedInteger('duration_minutes')->default(10);
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('slug', 'uq_lessons_slug');
            $table->index('category', 'idx_lessons_category');
            $table->index('is_published', 'idx_lessons_published');
            $table->index('order_index', 'idx_lessons_order');
            $table->foreign('created_by', 'fk_lessons_created_by')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
    }
};
