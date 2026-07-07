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
        // Now derived from whether s3_endpoint is set (blank = real AWS S3
        // = virtual-hosted-style; set = R2/MinIO/Spaces/self-hosted = almost
        // always path-style) instead of being a separate user-facing toggle.
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->dropColumn('use_path_style_endpoint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backup_settings', function (Blueprint $table) {
            $table->boolean('use_path_style_endpoint')->default(true);
        });
    }
};
