<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marks students whose printed ID card has been collected ("taken"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('id_taken_at')->nullable()->after('photo_path');
            $table->foreignId('id_taken_by')->nullable()->after('id_taken_at')->constrained('users')->nullOnDelete();
            $table->index(['school_id', 'id_taken_at']);
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['school_id', 'id_taken_at']);
            $table->dropConstrainedForeignId('id_taken_by');
            $table->dropColumn('id_taken_at');
        });
    }
};
