<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // school_id NULL = global template managed by the super admin, usable by every school.
        Schema::create('id_card_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 20); // STUDENT_ID | STAFF_ID
            $table->decimal('width_mm', 6, 2)->default(85.60);
            $table->decimal('height_mm', 6, 2)->default(53.98);
            $table->string('orientation', 12)->default('landscape');
            $table->unsignedSmallInteger('dpi')->default(300);
            $table->boolean('has_back')->default(true);
            $table->json('design_json');
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_id', 'type', 'status']);
        });

        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('paper_size', 12)->default('A4'); // A4 | A5 | LETTER | CUSTOM
            $table->string('orientation', 12)->default('landscape');
            $table->decimal('width_mm', 6, 2)->default(297);
            $table->decimal('height_mm', 6, 2)->default(210);
            $table->json('design_json');
            $table->string('default_title')->nullable();
            $table->text('default_body')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
        Schema::dropIfExists('id_card_templates');
    }
};
