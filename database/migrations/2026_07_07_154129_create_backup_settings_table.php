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
        // Single-row settings table (this is single-admin, instance-wide
        // config, not per-project) — BackupSetting::current() enforces the
        // singleton via firstOrCreate.
        Schema::create('backup_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('s3_endpoint')->nullable();
            $table->string('s3_region')->default('auto');
            $table->string('s3_bucket')->nullable();
            $table->text('s3_access_key_id')->nullable();
            $table->text('s3_secret_access_key')->nullable();
            $table->string('s3_path_prefix')->nullable();
            $table->boolean('use_path_style_endpoint')->default(true);
            $table->unsignedInteger('retention_count')->default(7);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_settings');
    }
};
