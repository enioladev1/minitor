<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queued_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('job_id')->nullable();
            $table->string('name');
            $table->string('connection')->nullable();
            $table->string('queue')->nullable();
            $table->unsignedBigInteger('duration_us')->default(0);
            $table->string('trace_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'queue']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queued_jobs');
    }
};
