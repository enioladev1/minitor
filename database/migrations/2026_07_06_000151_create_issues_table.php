<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // Nightwatch already computes a stable grouping hash per exception
            // (class + code + file + line) as the `_group` field on every
            // exception record - we reuse it directly as our fingerprint
            // rather than recomputing our own.
            $table->string('fingerprint', 32);
            $table->string('class');
            $table->string('file');
            $table->unsignedInteger('line')->default(0);
            $table->text('last_message')->nullable();
            $table->unsignedBigInteger('occurrences_count')->default(0);
            $table->string('status', 20)->default('open');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['project_id', 'fingerprint']);
            $table->index(['project_id', 'last_seen_at']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
