<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_attempts', function (Blueprint $table) {
            $table->string('user_external_id')->nullable()->after('status');
        });

        Schema::table('queued_jobs', function (Blueprint $table) {
            $table->string('user_external_id')->nullable()->after('queue');
        });
    }

    public function down(): void
    {
        Schema::table('job_attempts', function (Blueprint $table) {
            $table->dropColumn('user_external_id');
        });

        Schema::table('queued_jobs', function (Blueprint $table) {
            $table->dropColumn('user_external_id');
        });
    }
};
