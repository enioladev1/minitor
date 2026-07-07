<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            // A stable grouping key computed at ingest time by collapsing
            // variable-length `in (...)` clauses, so e.g. a 3-value and a
            // 5-value IN clause of the same query group together - mirrors
            // Nightwatch's own `_group` hash for queries.
            $table->text('query_group')->nullable()->after('sql');
            $table->index(['project_id', 'query_group']);
        });
    }

    public function down(): void
    {
        Schema::table('queries', function (Blueprint $table) {
            $table->dropColumn('query_group');
        });
    }
};
