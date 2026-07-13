<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the 7 request execution stages (Laravel\Nightwatch\ExecutionStage)
 * this query/cache event happened during - already sent on the wire, needed
 * to nest these under the right stage bar in the per-request Timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->string('execution_stage', 30)->nullable()->after('execution_source');
        });

        Schema::table('cache_events', function (Blueprint $table) {
            $table->string('execution_stage', 30)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->dropColumn('execution_stage');
        });

        Schema::table('cache_events', function (Blueprint $table) {
            $table->dropColumn('execution_stage');
        });
    }
};
