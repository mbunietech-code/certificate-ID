<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name', 20);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_current']);
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admission_number', 40);
            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 60)->nullable();
            $table->string('level', 20)->nullable(); // e.g. O-Level, A-Level
            $table->string('class_name', 40);
            $table->string('stream', 30)->nullable();
            $table->string('combination', 40)->nullable();
            $table->unsignedSmallInteger('entry_year')->nullable();
            $table->unsignedSmallInteger('completion_year')->nullable(); // expected graduation year
            $table->string('parent_name', 120)->nullable();
            $table->string('parent_phone', 40)->nullable();
            $table->string('student_phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'admission_number']);
            $table->index(['school_id', 'class_name', 'stream']);
            $table->index(['school_id', 'level']);
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'last_name', 'first_name']);
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 40);
            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('job_title', 80)->nullable();
            $table->string('department', 80)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('employment_status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'employee_number']);
            $table->index(['school_id', 'department']);
            $table->index(['school_id', 'employment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
        Schema::dropIfExists('students');
        Schema::dropIfExists('academic_years');
    }
};
