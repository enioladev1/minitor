<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('level', 20);
            $table->text('message')->nullable();
            $table->text('context')->nullable();
            $table->text('extra')->nullable();
            $table->string('trace_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
