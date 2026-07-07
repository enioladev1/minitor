<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('store')->nullable();
            $table->string('key')->nullable();
            $table->string('type', 20);
            $table->unsignedBigInteger('duration_us')->default(0);
            $table->unsignedInteger('ttl')->default(0);
            $table->string('trace_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_events');
    }
};
