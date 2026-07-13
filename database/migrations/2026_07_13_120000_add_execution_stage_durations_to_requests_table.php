<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nightwatch's `request` record already carries these 7 stage-duration
 * fields (bootstrap, before_middleware, action, render, after_middleware,
 * sending, terminating - see Laravel\Nightwatch\ExecutionStage) on the wire
 * today; MINITOR just wasn't persisting them. They're what the per-request
 * Timeline waterfall is built from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->unsignedBigInteger('bootstrap_us')->default(0)->after('duration_us');
            $table->unsignedBigInteger('before_middleware_us')->default(0)->after('bootstrap_us');
            $table->unsignedBigInteger('action_us')->default(0)->after('before_middleware_us');
            $table->unsignedBigInteger('render_us')->default(0)->after('action_us');
            $table->unsignedBigInteger('after_middleware_us')->default(0)->after('render_us');
            $table->unsignedBigInteger('sending_us')->default(0)->after('after_middleware_us');
            $table->unsignedBigInteger('terminating_us')->default(0)->after('sending_us');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn([
                'bootstrap_us', 'before_middleware_us', 'action_us',
                'render_us', 'after_middleware_us', 'sending_us', 'terminating_us',
            ]);
        });
    }
};
