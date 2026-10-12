<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The `users` table already exists in this project's database (created by the
 * original Node/MySQL schema), so this migration is GUARDED: on an existing
 * database it is a no-op and your data is left untouched. On a fresh database
 * it creates the same structure.
 *
 * NOTE: unlike a stock Laravel app there is no `email_verified_at` and no
 * `remember_token` — the app therefore never uses "remember me".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 120);
            $table->string('email', 180);
            $table->string('password', 255)->comment('bcrypt hash — never store plain text');
            $table->enum('role', ['student', 'admin'])->default('student');
            $table->string('avatar', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('country', 80)->nullable();
            $table->decimal('target_band', 3, 1)->nullable()->comment('Student goal band score 0.0 - 6.0');
            $table->string('language', 8)->default('en')->comment('UI language: en | my (Myanmar)');
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique('email', 'uq_users_email');
            $table->index('role', 'idx_users_role');
        });
    }

    public function down(): void
    {
        // Deliberately empty: dropping `users` would destroy real data.
    }
};
