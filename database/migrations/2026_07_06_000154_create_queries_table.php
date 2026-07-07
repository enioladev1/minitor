<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->text('sql');
            $table->string('file')->nullable();
            $table->unsignedInteger('line')->default(0);
            $table->unsignedBigInteger('duration_us');
            $table->string('connection')->nullable();
            $table->string('connection_type', 20)->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'duration_us']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queries');
    }
};
