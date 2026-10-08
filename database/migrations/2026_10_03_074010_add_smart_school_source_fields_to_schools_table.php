<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->string('smart_school_source', 20)->nullable()->after('status');
            $table->string('smart_school_endpoint_url', 500)->nullable()->after('smart_school_source');
            $table->text('smart_school_api_token')->nullable()->after('smart_school_endpoint_url');
            $table->string('smart_school_database', 100)->nullable()->after('smart_school_api_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn([
                'smart_school_source',
                'smart_school_endpoint_url',
                'smart_school_api_token',
                'smart_school_database',
            ]);
        });
    }
};
