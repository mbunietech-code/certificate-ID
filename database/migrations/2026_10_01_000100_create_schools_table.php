<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('school_code', 20)->unique();
            $table->string('name');
            $table->string('short_name', 60)->nullable();
            $table->string('registration_number', 60)->nullable();
            $table->text('address')->nullable();
            $table->string('region', 80)->nullable();
            $table->string('district', 80)->nullable();
            $table->string('ward', 80)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('principal_name')->nullable();
            $table->string('principal_signature_path')->nullable();
            $table->string('school_stamp_path')->nullable();
            $table->string('primary_color', 7)->default('#1e3a8a');
            $table->string('secondary_color', 7)->default('#b45309');
            // Numbering formats. Tokens: {CODE} {YEAR} {SEQ:n} {TYPE}
            $table->string('student_id_format', 80)->default('{CODE}/{YEAR}/{SEQ:4}');
            $table->string('staff_id_format', 80)->default('{CODE}/STF/{YEAR}/{SEQ:4}');
            $table->string('certificate_number_format', 80)->default('{CODE}/CERT/{YEAR}/{SEQ:5}');
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
