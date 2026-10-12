<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guarded — no-op when the table already exists (the normal case here). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_logs')) {
            return;
        }

        Schema::create('ai_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('feature', 60)->comment('generate_questions | score_writing | explain_answer ...');
            $table->string('model', 80)->nullable();
            $table->mediumText('prompt')->nullable();
            $table->mediumText('response')->nullable();
            $table->unsignedInteger('tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('success')->default(true);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id', 'idx_ai_logs_user');
            $table->index('feature', 'idx_ai_logs_feature');
        });
    }

    public function down(): void
    {
    }
};
