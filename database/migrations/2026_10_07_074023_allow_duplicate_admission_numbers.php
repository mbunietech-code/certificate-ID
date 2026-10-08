<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admission numbers are no longer unique: students without one are entered as
 * "11111". The plain index keeps lookups by admission number fast.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add the plain index first: MySQL needs an index for the school_id foreign key at all times.
        Schema::table('students', function (Blueprint $table) {
            $table->index(['school_id', 'admission_number'], 'students_school_admission_index');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'admission_number']);
        });
    }

    public function down(): void
    {
        // Fails if duplicate admission numbers exist; remove or renumber them first.
        Schema::table('students', function (Blueprint $table) {
            $table->unique(['school_id', 'admission_number']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('students_school_admission_index');
        });
    }
};
