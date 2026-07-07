<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('url', 2048);
            $table->string('route_name')->nullable();
            $table->string('route_path')->nullable();
            $table->string('route_action')->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->unsignedBigInteger('duration_us');
            $table->unsignedBigInteger('request_size')->default(0);
            $table->unsignedBigInteger('response_size')->default(0);
            $table->string('deploy')->nullable();
            $table->string('trace_id')->nullable();
            // The monitored app's own user id (from Nightwatch's `user` field),
            // if a user resolver is configured there — null means guest/unauthenticated.
            $table->string('user_external_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['project_id', 'occurred_at']);
            $table->index(['project_id', 'route_path']);
            $table->index(['project_id', 'status_code']);
            $table->index(['project_id', 'user_external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
