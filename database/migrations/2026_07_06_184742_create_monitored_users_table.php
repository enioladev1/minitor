<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitored_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // The monitored app's own user id (any type — stored as string).
            $table->string('external_id');
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['project_id', 'external_id']);
            $table->index(['project_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitored_users');
    }
};
