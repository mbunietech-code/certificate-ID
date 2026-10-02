<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Row-locked counters guarantee unique, gap-free numbers per school/type/period.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('period', 20);
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
            $table->unique(['school_id', 'type', 'period']);
        });

        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->morphs('holder'); // student | staff
            $table->foreignId('template_id')->nullable()->constrained('id_card_templates')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('card_number', 60)->unique();
            $table->string('verification_code', 32)->unique();
            $table->timestamp('issued_at');
            $table->date('expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['school_id', 'created_at']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('certificate_templates')->nullOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('certificate_number', 60)->unique();
            $table->string('verification_code', 32)->unique();
            $table->string('title');
            $table->string('recipient_name');
            $table->string('program')->nullable();
            $table->text('description')->nullable();
            $table->date('issued_on');
            $table->string('status', 20)->default('valid');
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_reason')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'issued_on']);
            $table->index(['school_id', 'created_at']);
        });

        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 30)->unique();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // student_id | staff_id | certificate
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_name')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('completed_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->string('status', 20)->default('pending');
            $table->json('options')->nullable();
            $table->string('file_path')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['school_id', 'status', 'created_at']);
            $table->index(['school_id', 'type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::create('print_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->morphs('subject'); // the student/staff the document is for
            $table->nullableMorphs('printable'); // the id_card/certificate produced
            $table->string('status', 20)->default('pending');
            $table->string('error')->nullable();
            $table->timestamps();
            $table->index(['print_job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_job_items');
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('id_cards');
        Schema::dropIfExists('number_sequences');
    }
};
