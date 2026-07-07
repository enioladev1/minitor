<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('code', 50)->nullable();
            $table->boolean('handled')->default(false);
            $table->text('trace')->nullable();
            $table->string('trace_id')->nullable();
            $table->string('php_version', 20)->nullable();
            $table->string('laravel_version', 20)->nullable();
            $table->string('user_external_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['project_id', 'occurred_at']);
            $table->index(['issue_id', 'occurred_at']);
            $table->index(['project_id', 'user_external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exceptions');
    }
};
