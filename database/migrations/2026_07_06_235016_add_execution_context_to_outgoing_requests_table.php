<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outgoing_requests', function (Blueprint $table) {
            $table->string('execution_source')->nullable()->after('status_code');
            $table->string('execution_preview')->nullable()->after('execution_source');
        });
    }

    public function down(): void
    {
        Schema::table('outgoing_requests', function (Blueprint $table) {
            $table->dropColumn(['execution_source', 'execution_preview']);
        });
    }
};
