<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 60)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 80)->unique();
            $table->string('group_name', 60)->index();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('school_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            // Nullable only so SQLite can add the column to an existing table; always required by the app.
            $table->foreignId('role_id')->nullable()->after('school_id')->constrained()->restrictOnDelete();
            $table->string('phone', 40)->nullable()->after('email');
            $table->string('status', 20)->default('active')->after('password');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('school_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['phone', 'status', 'last_login_at', 'last_login_ip']);
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
