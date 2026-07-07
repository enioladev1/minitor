<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('cron')->nullable();
            $table->string('timezone')->nullable();
            $table->unsignedInteger('repeat_seconds')->default(0);
            $table->boolean('without_overlapping')->default(false);
            $table->boolean('on_one_server')->default(false);
            $table->boolean('run_in_background')->default(false);
            $table->boolean('even_in_maintenance_mode')->default(false);
            $table->string('status', 20);
            $table->unsignedBigInteger('duration_us')->default(0);
            $table->string('trace_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['project_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};
