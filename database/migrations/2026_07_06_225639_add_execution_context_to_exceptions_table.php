<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exceptions', function (Blueprint $table) {
            $table->string('server')->nullable()->after('user_external_id');
            $table->string('execution_source')->nullable()->after('server');
            $table->string('execution_preview')->nullable()->after('execution_source');
        });
    }

    public function down(): void
    {
        Schema::table('exceptions', function (Blueprint $table) {
            $table->dropColumn(['server', 'execution_source', 'execution_preview']);
        });
    }
};
