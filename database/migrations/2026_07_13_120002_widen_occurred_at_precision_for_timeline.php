<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `occurred_at` on requests/queries/cache_events defaulted to whole-second
 * precision (Laravel's timestamp() default). Positioning a query/cache bar
 * inside a request's Timeline waterfall needs microsecond offsets, which
 * Nightwatch already sends - this just stops Postgres from truncating them
 * on write.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE requests ALTER COLUMN occurred_at TYPE timestamp(6)');
        DB::statement('ALTER TABLE queries ALTER COLUMN occurred_at TYPE timestamp(6)');
        DB::statement('ALTER TABLE cache_events ALTER COLUMN occurred_at TYPE timestamp(6)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE requests ALTER COLUMN occurred_at TYPE timestamp(0)');
        DB::statement('ALTER TABLE queries ALTER COLUMN occurred_at TYPE timestamp(0)');
        DB::statement('ALTER TABLE cache_events ALTER COLUMN occurred_at TYPE timestamp(0)');
    }
};
