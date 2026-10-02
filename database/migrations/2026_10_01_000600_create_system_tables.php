<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 20); // certificate | id_card
            $table->string('lookup', 80);
            $table->unsignedBigInteger('document_id')->nullable();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 20); // valid | invalid | not_found
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['school_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('entity_type', 60)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('description')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['school_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('student_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('status', 20)->default('preview'); // preview | completed | cancelled
            $table->string('duplicate_mode', 10)->default('skip'); // skip | update
            $table->longText('rows');
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_imports');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('verification_logs');
    }
};
